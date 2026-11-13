<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Events\DeliveryCompleted;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Payments\Contracts\CashDueReader;
use Illuminate\Support\Facades\DB;

final class ConfirmDeliveryAction
{
    public function __construct(
        private readonly OrderDeliveryPort $orders,
        private readonly CashDueReader $cash,
    ) {}

    /**
     * Remise de la commande : le code donné par le client est vérifié (limité à 5 essais), puis l'argent
     * encaissé doit correspondre exactement au montant dû en paiement à la livraison.
     *
     * @throws DeliveryNotAllowed
     */
    public function execute(Delivery $delivery, int $courierId, string $code, int $cashCollectedMinor): Delivery
    {
        $this->verifyCode($delivery, $courierId, $code);

        $due = $this->cash->cashDue($delivery->order_id);
        if ($cashCollectedMinor !== $due) {
            throw DeliveryNotAllowed::cashMismatch($due);
        }

        $done = DB::transaction(function () use ($delivery, $courierId, $cashCollectedMinor) {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DeliveryStatus::OutForDelivery) {
                throw DeliveryNotAllowed::wrongState('confirmation');
            }

            try {
                $this->orders->markDelivered($locked->order_id, $courierId);
            } catch (InvalidOrderTransition) {
                throw DeliveryNotAllowed::wrongState('confirmation');
            }

            $locked->update([
                'status' => DeliveryStatus::Delivered, 'delivered_at' => now(),
                'cash_collected_minor' => $cashCollectedMinor > 0 ? $cashCollectedMinor : null,
            ]);
            $locked->location()->delete(); // la position du livreur ne se conserve pas après la remise

            return $locked;
        });

        DeliveryCompleted::dispatch($done->order_id, $courierId, $cashCollectedMinor);

        return $done;
    }

    /**
     * Un mauvais code incrémente le compteur AVANT de refuser, et ce compteur est enregistré même si la
     * requête échoue : on ne peut pas deviner un code à 6 chiffres par essais répétés.
     */
    private function verifyCode(Delivery $delivery, int $courierId, string $code): void
    {
        $fresh = Delivery::whereKey($delivery->id)->firstOrFail();

        if ($fresh->courier_id !== $courierId || $fresh->status !== DeliveryStatus::OutForDelivery) {
            throw DeliveryNotAllowed::wrongState('confirmation');
        }

        if ($fresh->isCodeLocked()) {
            throw DeliveryNotAllowed::codeLocked();
        }

        if (! hash_equals($fresh->confirmation_code, $code)) {
            Delivery::whereKey($fresh->id)->increment('code_attempts');
            $remaining = max(0, Delivery::MAX_CODE_ATTEMPTS - $fresh->code_attempts - 1);

            throw $remaining === 0 ? DeliveryNotAllowed::codeLocked() : DeliveryNotAllowed::wrongCode($remaining);
        }
    }
}
