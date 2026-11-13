<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

final class CollectCashOnDeliveryAction
{
    /**
     * Le livreur a encaissé le paiement à la livraison : le paiement devient « réussi » pour le montant réellement
     * encaissé (le total réel après préparation), ce qui déclenche l'écriture de caisse. Idempotent.
     */
    public function execute(int $orderId, int $collectedMinor): ?Payment
    {
        $payment = DB::transaction(function () use ($orderId, $collectedMinor) {
            $payment = Payment::where('order_id', $orderId)->where('status', PaymentStatus::PendingOnDelivery)->lockForUpdate()->first();

            if ($payment === null || $collectedMinor <= 0) {
                return null;
            }

            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'amount_minor' => $collectedMinor,
                'note' => 'Encaissé en espèces par le livreur à la livraison',
            ]);

            return $payment;
        });

        if ($payment !== null) {
            PaymentSucceeded::dispatch($payment->id, $payment->order_id, $payment->amount_minor, $payment->currency, $payment->method);
        }

        return $payment;
    }
}
