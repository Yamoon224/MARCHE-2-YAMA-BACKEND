<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Identity\Contracts\CourierDirectory;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use Illuminate\Support\Facades\DB;

final class AssignCourierAction
{
    public function __construct(
        private readonly OrderDeliveryPort $orders,
        private readonly CourierDirectory $couriers,
    ) {}

    /**
     * Confie une livraison prête à un livreur (ou la réaffecte tant qu'elle n'est pas partie).
     *
     * @throws DeliveryNotAllowed
     */
    public function execute(Delivery $delivery, int $courierId, int $managerId): Delivery
    {
        if (! $this->couriers->isCourier($courierId)) {
            throw DeliveryNotAllowed::notACourier();
        }

        return DB::transaction(function () use ($delivery, $courierId, $managerId) {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [DeliveryStatus::Pending, DeliveryStatus::Assigned], true)) {
                throw DeliveryNotAllowed::wrongState('affectation');
            }

            // Première affectation : la commande passe « livreur assigné ». Réaffectation : elle l'est déjà.
            if ($locked->status === DeliveryStatus::Pending) {
                try {
                    $this->orders->markAssigned($locked->order_id, $managerId);
                } catch (InvalidOrderTransition) {
                    throw DeliveryNotAllowed::wrongState('commande non prête');
                }
            }

            $locked->update(['courier_id' => $courierId, 'status' => DeliveryStatus::Assigned, 'assigned_at' => now()]);

            return $locked;
        });
    }
}
