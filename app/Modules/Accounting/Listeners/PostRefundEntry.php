<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Actions\PostEntryAction;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Events\RefundIssued;

/** Constate l'argent rendu au client : Débit 411 Clients / Crédit trésorerie (mobile money, banque, caisse). */
final class PostRefundEntry
{
    public function __construct(private readonly PostEntryAction $post) {}

    public function handle(RefundIssued $event): void
    {
        $treasury = match ($event->method) {
            PaymentMethod::MobileMoney => '5851',
            PaymentMethod::Card => '521',
            PaymentMethod::CashOnDelivery, PaymentMethod::Cash => '571',
        };

        $this->post->execute('BQ', 'refund', $event->refundId, "Remboursement commande {$event->orderId}", [
            ['account' => '411', 'debit' => $event->amountMinor],
            ['account' => $treasury, 'credit' => $event->amountMinor],
        ]);
    }
}
