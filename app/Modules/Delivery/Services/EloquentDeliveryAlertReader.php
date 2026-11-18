<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Reporting\Contracts\DeliveryAlertReader;
use Illuminate\Support\Facades\DB;

final class EloquentDeliveryAlertReader implements DeliveryAlertReader
{
    public function lateDeliveries(): int
    {
        return DB::table('deliveries')
            ->join('slot_bookings', 'slot_bookings.order_id', '=', 'deliveries.order_id')
            ->join('delivery_slots', 'delivery_slots.id', '=', 'slot_bookings.delivery_slot_id')
            ->whereIn('deliveries.status', [DeliveryStatus::Pending->value, DeliveryStatus::Assigned->value, DeliveryStatus::OutForDelivery->value])
            ->where('delivery_slots.ends_at', '<', now())
            ->count();
    }
}
