<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Actions;

use App\Modules\Fulfillment\Enums\PickLineStatus;
use App\Modules\Fulfillment\Enums\PickListStatus;
use App\Modules\Fulfillment\Models\PickList;
use App\Modules\Orders\Contracts\OrderPickingPort;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class CreatePickListAction
{
    public function __construct(private readonly OrderPickingPort $orders) {}

    /** Crée la liste de préparation d'une commande, une seule fois (idempotent). Retourne null si la commande n'existe pas. */
    public function execute(int $orderId): ?PickList
    {
        $existing = PickList::where('order_id', $orderId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $sources = $this->orders->linesFor($orderId);
        if ($sources === null) {
            return null;
        }

        try {
            return DB::transaction(function () use ($orderId, $sources) {
                $list = PickList::create(['order_id' => $orderId, 'status' => PickListStatus::Open]);

                $list->lines()->createMany(array_map(fn ($s) => [
                    'order_line_id' => $s->orderLineId, 'product_id' => $s->productId, 'name' => $s->name,
                    'sale_unit' => $s->saleUnit, 'ordered_quantity' => $s->quantity,
                    'unit_price_minor' => $s->unitPriceMinor, 'substitution' => $s->substitution,
                    'status' => PickLineStatus::Pending,
                ], $sources));

                return $list;
            });
        } catch (UniqueConstraintViolationException) {
            return PickList::where('order_id', $orderId)->first();
        }
    }
}
