<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Listeners;

use App\Modules\Invoicing\Actions\GenerateCreditNoteAction;
use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Events\RefundIssued;

/**
 * Un geste commercial ou une réclamation remboursés au client donnent un avoir. Les autres remboursements
 * (écart de préparation, annulation) ont déjà leur avoir ; un paiement tardif n'en a pas besoin (facture déjà créditée).
 */
final class CreditOnManualRefund
{
    public function __construct(private readonly GenerateCreditNoteAction $credit) {}

    public function handle(RefundIssued $event): void
    {
        if (in_array($event->kind, [RefundKind::Manual, RefundKind::Claim], true)) {
            $this->credit->execute($event->orderId, $event->amountMinor, $event->kind === RefundKind::Claim ? 'Réclamation acceptée' : 'Remboursement commercial', "refund:{$event->refundId}");
        }
    }
}
