<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\Templates;
use App\Modules\Orders\Contracts\OrderCustomerDirectory;
use App\Modules\Payments\Events\RefundIssued;
use Throwable;

final class NotifyOnRefund
{
    public function __construct(
        private readonly OrderCustomerDirectory $orders,
        private readonly SendNotificationAction $send,
    ) {}

    public function handle(RefundIssued $event): void
    {
        try {
            $customerId = $this->orders->customersOf([$event->orderId])[$event->orderId] ?? null;

            if ($customerId !== null && $event->amountMinor > 0) {
                $this->send->execute($customerId, Templates::REFUND_SENT, ['order_id' => $event->orderId, 'amount_minor' => $event->amountMinor], "refund:{$event->refundId}");
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
