<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Listeners;

use App\Modules\Orders\Contracts\StockReservationPort;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;

/** Répercute le cycle de vie d'une commande sur le stock. */
final class SyncStockWithOrderStatus
{
    public function __construct(private readonly StockReservationPort $stock) {}

    public function handle(OrderStatusChanged $event): void
    {
        $reference = "order:{$event->orderId}";

        match ($event->to) {
            OrderStatus::Paid => $this->stock->hold($reference),
            OrderStatus::Cancelled, OrderStatus::Returned => $this->stock->release($reference),
            OrderStatus::Delivered => $this->stock->commit($reference),
            default => null,
        };
    }
}
