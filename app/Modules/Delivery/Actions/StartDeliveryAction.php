<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use Illuminate\Support\Facades\DB;

final class StartDeliveryAction
{
    public function __construct(private readonly OrderDeliveryPort $orders) {}

    /**
     * Le livreur part (ou repart après un échec) : la commande passe « en cours de livraison ».
     *
     * @throws DeliveryNotAllowed
     */
    public function execute(Delivery $delivery, int $courierId): Delivery
    {
        return DB::transaction(function () use ($delivery, $courierId) {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($locked->courier_id !== $courierId || ! in_array($locked->status, [DeliveryStatus::Assigned, DeliveryStatus::Failed], true)) {
                throw DeliveryNotAllowed::wrongState('départ');
            }

            try {
                $this->orders->markOutForDelivery($locked->order_id, $courierId);
            } catch (InvalidOrderTransition) {
                throw DeliveryNotAllowed::wrongState('départ');
            }

            $locked->update(['status' => DeliveryStatus::OutForDelivery, 'failure_reason' => null]);

            return $locked;
        });
    }
}
