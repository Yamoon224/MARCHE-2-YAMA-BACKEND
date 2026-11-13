<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryLocation;

final class RecordLocationAction
{
    /**
     * Enregistre la position du livreur, uniquement pendant la livraison. Une seule position est conservée
     * (la dernière) : pas d'historique de déplacements.
     *
     * @throws DeliveryNotAllowed
     */
    public function execute(Delivery $delivery, int $courierId, float $latitude, float $longitude): void
    {
        if ($delivery->courier_id !== $courierId || $delivery->status !== DeliveryStatus::OutForDelivery) {
            throw DeliveryNotAllowed::wrongState('position');
        }

        DeliveryLocation::updateOrCreate(
            ['delivery_id' => $delivery->id],
            ['latitude' => $latitude, 'longitude' => $longitude, 'recorded_at' => now()],
        );
    }
}
