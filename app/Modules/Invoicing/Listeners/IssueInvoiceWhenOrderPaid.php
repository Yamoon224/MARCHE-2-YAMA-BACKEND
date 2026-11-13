<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Listeners;

use App\Modules\Invoicing\Actions\GenerateInvoiceAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;

final class IssueInvoiceWhenOrderPaid
{
    public function __construct(private readonly GenerateInvoiceAction $generate) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to === OrderStatus::Paid) {
            $this->generate->execute($event->orderId);
        }
    }
}
