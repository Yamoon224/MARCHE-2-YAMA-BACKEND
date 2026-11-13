<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Actions\PostEntryAction;
use App\Modules\Invoicing\Events\InvoiceIssued;

/** Constate la vente : Débit 411 Clients (TTC) / Crédit 701 Ventes (HT) + Crédit 4431 TVA collectée. */
final class PostInvoiceEntry
{
    public function __construct(private readonly PostEntryAction $post) {}

    public function handle(InvoiceIssued $event): void
    {
        $lines = [
            ['account' => '411', 'debit' => $event->totalMinor],
            ['account' => '701', 'credit' => $event->netMinor],
        ];

        if ($event->vatMinor > 0) {
            $lines[] = ['account' => '4431', 'credit' => $event->vatMinor];
        }

        $this->post->execute('VT', 'invoice', $event->invoiceId, "Facture {$event->number}", $lines);
    }
}
