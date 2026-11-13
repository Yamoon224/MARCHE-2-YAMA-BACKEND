<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Actions\PlaceOrderAction;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Contracts\PosOrderPort;
use App\Modules\Orders\DTO\PosOrder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

final class EloquentPosOrderPort implements PosOrderPort
{
    public function __construct(
        private readonly PlaceOrderAction $place,
        private readonly TransitionOrderAction $transition,
    ) {}

    public function place(int $cashierId, array $items): PosOrder
    {
        $order = $this->place->execute($cashierId, $items, null, null, 'pos');

        return new PosOrder(
            $order->id,
            (string) $order->number,
            $order->total_minor,
            $order->lines->map(fn ($l) => [
                'name' => $l->name, 'quantity' => $l->quantity, 'sale_unit' => (string) $l->sale_unit->value,
                'unit_price_minor' => $l->unit_price_minor, 'line_total_minor' => $l->line_total_minor,
            ])->values()->all(),
        );
    }

    public function completeInStore(int $orderId, int $cashierId): void
    {
        $order = Order::findOrFail($orderId);

        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Delivered] as $status) {
            $order = $this->transition->execute($order, $status, $cashierId);
        }
    }
}
