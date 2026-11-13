<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\PaymentNotAllowed;
use App\Modules\Payments\Models\Payment;

final class ValidateCashOnDeliveryAction
{
    public function __construct(private readonly OrderPaymentPort $orders) {}

    /**
     * Validation par l'opérateur (appel ou message) d'une commande à payer à la livraison :
     * la commande passe « payée » (donc maintenue en stock) mais l'argent reste à encaisser.
     *
     * @throws PaymentNotAllowed
     */
    public function execute(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::PendingOnDelivery) {
            throw PaymentNotAllowed::notCashOnDelivery();
        }

        if (! $this->orders->markPaid($payment->order_id)) {
            throw PaymentNotAllowed::orderNotPayable('annulée');
        }

        $payment->update(['note' => 'Validée par confirmation téléphonique']);

        return $payment;
    }
}
