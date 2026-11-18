<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Identity\Contracts\CourierDirectory;
use App\Modules\Orders\Contracts\OrderDetailProvider;

final class DeliveryOrderDetailProvider implements OrderDetailProvider
{
    public function __construct(private readonly CourierDirectory $couriers) {}

    public function key(): string
    {
        return 'delivery';
    }

    public function detailsFor(int $orderId): ?array
    {
        $delivery = Delivery::where('order_id', $orderId)->first();

        if ($delivery === null) {
            return null;
        }

        return [
            'status' => $delivery->status->value,
            'courier_name' => $delivery->courier_id !== null ? $this->couriers->nameOf($delivery->courier_id) : null,
            'cash_collected_minor' => $delivery->cash_collected_minor,
            'failure_reason' => $delivery->failure_reason,
            'assigned_at' => $delivery->assigned_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
        ];
    }
}
