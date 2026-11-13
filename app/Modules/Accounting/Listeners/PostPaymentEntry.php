<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Actions\PostEntryAction;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Events\PaymentSucceeded;

/** Constate l'encaissement : Débit trésorerie (mobile money, banque, caisse) / Crédit 411 Clients. */
final class PostPaymentEntry
{
    public function __construct(private readonly PostEntryAction $post) {}

    public function handle(PaymentSucceeded $event): void
    {
        $treasury = match ($event->method) {
            PaymentMethod::MobileMoney => '5851',
            PaymentMethod::Card => '521',
            PaymentMethod::CashOnDelivery => '571',
        };

        $this->post->execute('BQ', 'payment', $event->paymentId, "Encaissement commande {$event->orderId}", [
            ['account' => $treasury, 'debit' => $event->amountMinor],
            ['account' => '411', 'credit' => $event->amountMinor],
        ]);
    }
}
