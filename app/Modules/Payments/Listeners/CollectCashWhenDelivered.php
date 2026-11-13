<?php

declare(strict_types=1);

namespace App\Modules\Payments\Listeners;

use App\Modules\Delivery\Events\DeliveryCompleted;
use App\Modules\Payments\Actions\CollectCashOnDeliveryAction;

final class CollectCashWhenDelivered
{
    public function __construct(private readonly CollectCashOnDeliveryAction $collect) {}

    public function handle(DeliveryCompleted $event): void
    {
        if ($event->cashCollectedMinor > 0) {
            $this->collect->execute($event->orderId, $event->cashCollectedMinor);
        }
    }
}
