<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Actions\PostEntryAction;
use App\Modules\Invoicing\Events\CreditNoteIssued;

/** Annule tout ou partie de la vente : Débit 701 Ventes (HT) + Débit 4431 TVA / Crédit 411 Clients (TTC). */
final class PostCreditNoteEntry
{
    public function __construct(private readonly PostEntryAction $post) {}

    public function handle(CreditNoteIssued $event): void
    {
        $lines = [
            ['account' => '701', 'debit' => $event->netMinor],
            ['account' => '411', 'credit' => $event->totalMinor],
        ];

        if ($event->vatMinor > 0) {
            $lines[] = ['account' => '4431', 'debit' => $event->vatMinor];
        }

        // Un avoir dont le HT serait nul (TVA = TTC) n'aurait pas de ligne 701 : on ne poste que des lignes non nulles.
        $lines = array_values(array_filter($lines, fn (array $l) => ($l['debit'] ?? 0) > 0 || ($l['credit'] ?? 0) > 0));

        $this->post->execute('VT', 'credit_note', $event->creditNoteId, "Avoir {$event->number}", $lines);
    }
}
