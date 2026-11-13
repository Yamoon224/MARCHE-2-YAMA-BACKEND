<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use Illuminate\Support\Facades\DB;

final class FailDeliveryAction
{
    public function __construct(private readonly OrderDeliveryPort $orders) {}

    /**
     * Échec de la remise (client absent, adresse introuvable) avec motif obligatoire.
     * La livraison peut être retentée (le livreur repart) ou retournée au magasin.
     *
     * @throws DeliveryNotAllowed
     */
    public function execute(Delivery $delivery, int $courierId, string $reason): Delivery
    {
        return DB::transaction(function () use ($delivery, $courierId, $reason) {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($locked->courier_id !== $courierId || $locked->status !== DeliveryStatus::OutForDelivery) {
                throw DeliveryNotAllowed::wrongState('échec');
            }

            try {
                $this->orders->markDeliveryFailed($locked->order_id, $courierId, $reason);
            } catch (InvalidOrderTransition) {
                throw DeliveryNotAllowed::wrongState('échec');
            }

            $locked->update(['status' => DeliveryStatus::Failed, 'failure_reason' => $reason]);
            $locked->location()->delete();

            return $locked;
        });
    }
}
