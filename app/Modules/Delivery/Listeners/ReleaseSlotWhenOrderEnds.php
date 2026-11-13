<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Listeners;

use App\Modules\Delivery\Actions\ReleaseSlotAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;

final class ReleaseSlotWhenOrderEnds
{
    public function __construct(private readonly ReleaseSlotAction $release) {}

    public function handle(OrderStatusChanged $event): void
    {
        if (in_array($event->to, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
            $this->release->execute($event->orderId);
        }
    }
}
