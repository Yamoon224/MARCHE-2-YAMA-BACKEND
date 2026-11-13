<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Delivery\Models\SlotBooking;
use Illuminate\Support\Facades\DB;

final class ReleaseSlotAction
{
    /** Rend la place d'une commande annulée ; sans effet si elle n'en a pas ou plus (idempotent). */
    public function execute(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $booking = SlotBooking::where('order_id', $orderId)->lockForUpdate()->first();

            if ($booking === null) {
                return;
            }

            DeliverySlot::whereKey($booking->delivery_slot_id)->where('booked', '>', 0)->decrement('booked');
            $booking->delete();
        });
    }
}
