<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use Illuminate\Support\Facades\DB;

final class ReturnToStoreAction
{
    public function __construct(private readonly OrderDeliveryPort $orders) {}

    /**
     * Abandonne une livraison échouée : la commande est « retournée au magasin » (stock rendu, créneau libéré,
     * client remboursé et avoir émis par les modules concernés).
     *
     * @throws DeliveryNotAllowed
     */
    public function execute(Delivery $delivery, int $userId, string $reason): Delivery
    {
        return DB::transaction(function () use ($delivery, $userId, $reason) {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DeliveryStatus::Failed) {
                throw DeliveryNotAllowed::wrongState('retour');
            }

            try {
                $this->orders->markReturned($locked->order_id, $userId, $reason);
            } catch (InvalidOrderTransition) {
                throw DeliveryNotAllowed::wrongState('retour');
            }

            $locked->update(['status' => DeliveryStatus::Returned, 'failure_reason' => $reason]);

            return $locked;
        });
    }
}
