<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Contracts\OrderPickingPort;
use App\Modules\Orders\Contracts\StockReservationPort;
use App\Modules\Orders\Domain\LineTotalCalculator;
use App\Modules\Orders\DTO\PickResult;
use App\Modules\Orders\DTO\PickSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderTotalAdjusted;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

final class EloquentOrderPickingPort implements OrderPickingPort
{
    public function __construct(
        private readonly StockReservationPort $stock,
        private readonly LineTotalCalculator $calculator,
        private readonly TransitionOrderAction $transition,
    ) {}

    public function linesFor(int $orderId): ?array
    {
        $order = Order::with('lines')->find($orderId);

        if ($order === null) {
            return null;
        }

        return $order->lines->map(fn ($l) => new PickSource(
            orderLineId: $l->id, productId: $l->product_id, name: $l->name, saleUnit: $l->sale_unit,
            quantity: $l->quantity, unitPriceMinor: $l->unit_price_minor, substitution: $l->substitution,
        ))->all();
    }

    public function applyResult(int $orderId, array $results, ?int $pickerId): int
    {
        $result = DB::transaction(function () use ($orderId, $results) {
            $order = Order::with('lines')->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $reference = "order:{$order->id}";
            $byLine = collect($results)->keyBy(fn (PickResult $r) => $r->orderLineId);
            $itemsTotal = 0;

            foreach ($order->lines as $line) {
                /** @var PickResult $picked */
                $picked = $byLine->get($line->id);
                $total = $this->calculator->total($line->sale_unit, $picked->unitPriceMinor, $picked->quantity);
                $itemsTotal += $total;

                // Stock : on libère ce qui n'est pas servi, et on réserve le produit de remplacement le cas échéant.
                if ($picked->substituted) {
                    $this->stock->shrink($reference, $line->product_id, 0);
                    if ($picked->quantity > 0) {
                        $this->stock->reserve($reference, [$picked->productId => $picked->quantity], null);
                    }
                } elseif ($picked->quantity < $line->quantity) {
                    $this->stock->shrink($reference, $line->product_id, $picked->quantity);
                }

                $line->update([
                    'final_product_id' => $picked->productId, 'final_name' => $picked->name,
                    'final_quantity' => $picked->quantity, 'final_unit_price_minor' => $picked->unitPriceMinor,
                    'final_line_total_minor' => $total,
                ]);
            }

            $final = max(0, $itemsTotal + $order->delivery_fee_minor - $order->discount_minor);
            $delta = $final - $order->total_minor;
            $order->update(['final_total_minor' => $final, 'adjustment_minor' => $delta]);

            return [$order, $final, $delta];
        });

        [$order, $final, $delta] = $result;

        $this->transition->execute($order, OrderStatus::Ready, $pickerId);

        if ($delta !== 0) {
            OrderTotalAdjusted::dispatch($order->id, $order->total_minor, $final, $delta);
        }

        return $delta;
    }
}
