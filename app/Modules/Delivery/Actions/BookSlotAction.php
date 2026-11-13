<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Delivery\Models\SlotBooking;
use App\Modules\Orders\Exceptions\DeliveryUnavailable;
use Illuminate\Support\Facades\DB;

final class BookSlotAction
{
    /**
     * Réserve une place : la ligne du créneau est verrouillée, donc deux commandes simultanées
     * ne peuvent pas dépasser la capacité. Idempotent par commande.
     *
     * @throws DeliveryUnavailable
     */
    public function execute(int $slotId, int $zoneId, int $orderId): void
    {
        DB::transaction(function () use ($slotId, $zoneId, $orderId) {
            if (SlotBooking::where('order_id', $orderId)->exists()) {
                return;
            }

            $slot = DeliverySlot::whereKey($slotId)->lockForUpdate()->first();
            $earliest = now()->addMinutes((int) config('delivery.min_lead_minutes'));

            if ($slot === null
                || $slot->delivery_zone_id !== $zoneId
                || $slot->starts_at->lt($earliest)
                || $slot->remaining() === 0) {
                throw DeliveryUnavailable::slotUnavailable();
            }

            $slot->increment('booked');
            SlotBooking::create(['delivery_slot_id' => $slot->id, 'order_id' => $orderId]);
        });
    }
}
