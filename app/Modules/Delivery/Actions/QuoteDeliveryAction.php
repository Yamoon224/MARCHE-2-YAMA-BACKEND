<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Orders\DTO\DeliveryQuote;
use App\Modules\Orders\Exceptions\DeliveryUnavailable;

final class QuoteDeliveryAction
{
    /** @throws DeliveryUnavailable */
    public function execute(int $zoneId, int $subtotalMinor): DeliveryQuote
    {
        $zone = DeliveryZone::find($zoneId);

        if ($zone === null || ! $zone->is_active) {
            throw DeliveryUnavailable::zoneUnavailable();
        }

        if ($subtotalMinor < $zone->min_order_minor) {
            throw DeliveryUnavailable::minimumOrder($zone->min_order_minor);
        }

        $free = $zone->free_above_minor !== null && $subtotalMinor >= $zone->free_above_minor;

        return new DeliveryQuote($free ? 0 : $zone->fee_minor);
    }
}
