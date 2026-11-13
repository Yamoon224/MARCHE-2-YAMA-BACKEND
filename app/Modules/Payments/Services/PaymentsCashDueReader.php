<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Payments\Contracts\CashDueReader;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;

final class PaymentsCashDueReader implements CashDueReader
{
    public function __construct(private readonly OrderPaymentPort $orders) {}

    public function cashDue(int $orderId): int
    {
        $pending = Payment::where('order_id', $orderId)->where('status', PaymentStatus::PendingOnDelivery)->exists();

        if (! $pending) {
            return 0;
        }

        $order = $this->orders->findPayable($orderId);

        return $order === null ? 0 : $order->finalTotalMinor;
    }
}
