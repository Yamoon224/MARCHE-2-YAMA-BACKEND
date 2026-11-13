<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Actions\PostEntryAction;
use App\Modules\Pos\Events\PosSessionClosed;

/**
 * Fermeture de caisse : un manquant est une charge (658 / crédit 571), un excédent un produit (débit 571 / 758).
 * Les ventes elles-mêmes sont déjà comptabilisées une à une ; sans écart, aucune écriture.
 */
final class PostCashDrawerEntry
{
    public function __construct(private readonly PostEntryAction $post) {}

    public function handle(PosSessionClosed $event): void
    {
        $variance = $event->countedMinor - $event->expectedCashMinor;

        if ($variance === 0) {
            return;
        }

        $lines = $variance < 0
            ? [['account' => '658', 'debit' => -$variance], ['account' => '571', 'credit' => -$variance]]
            : [['account' => '571', 'debit' => $variance], ['account' => '758', 'credit' => $variance]];

        $this->post->execute('CA', 'pos_session', $event->sessionId, "Écart de caisse (session {$event->sessionId})", $lines);
    }
}
