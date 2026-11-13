<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Actions\PostEntryAction;
use App\Modules\Delivery\Events\CashHandedOver;

/**
 * Remise de fonds : Débit 571 Caisse (compté) / Crédit 5715 Espèces des livreurs (attendu).
 * Un manquant est une charge (658), un excédent un produit (758) : la différence n'est jamais effacée.
 */
final class PostCashHandoverEntry
{
    public function __construct(private readonly PostEntryAction $post) {}

    public function handle(CashHandedOver $event): void
    {
        $lines = [['account' => '5715', 'credit' => $event->expectedMinor]];

        if ($event->countedMinor > 0) {
            $lines[] = ['account' => '571', 'debit' => $event->countedMinor];
        }

        $variance = $event->countedMinor - $event->expectedMinor;

        if ($variance < 0) {
            $lines[] = ['account' => '658', 'debit' => -$variance];
        } elseif ($variance > 0) {
            $lines[] = ['account' => '758', 'credit' => $variance];
        }

        $this->post->execute('CA', 'cash_handover', $event->handoverId, "Remise de fonds du livreur {$event->courierId}", $lines);
    }
}
