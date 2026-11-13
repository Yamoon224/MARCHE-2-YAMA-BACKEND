<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Actions;

use App\Modules\Accounting\Models\JournalEntry;

final class ReverseEntryAction
{
    public function __construct(private readonly PostEntryAction $post) {}

    /** Contre-passation : une écriture inverse référence l'originale, qui reste intacte. Idempotente. */
    public function execute(JournalEntry $original, string $reason): JournalEntry
    {
        $lines = $original->lines->map(fn ($l) => [
            'account' => $l->account_code,
            'debit' => $l->credit_minor,
            'credit' => $l->debit_minor,
        ])->all();

        return $this->post->execute(
            $original->journal, 'reversal', $original->id,
            "Contre-passation de l'écriture {$original->id} : {$reason}", $lines, $original->id,
        );
    }
}
