<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Listeners;

use App\Modules\Fulfillment\Actions\CreatePickListAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;

final class CreatePickListWhenPreparing
{
    public function __construct(private readonly CreatePickListAction $create) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to === OrderStatus::Preparing) {
            $this->create->execute($event->orderId);
        }
    }
}
