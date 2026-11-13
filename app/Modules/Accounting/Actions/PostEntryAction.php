<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Actions;

use App\Modules\Accounting\Exceptions\UnbalancedEntry;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class PostEntryAction
{
    /**
     * Enregistre une écriture en partie double. Toujours équilibrée (débit = crédit), jamais modifiée ensuite.
     * Idempotente par source : rejouer le même événement ne crée pas de seconde écriture.
     *
     * @param  list<array{account: string, debit?: int, credit?: int}>  $lines
     *
     * @throws UnbalancedEntry
     */
    public function execute(string $journal, string $sourceType, int $sourceId, string $description, array $lines, ?int $reversalOfId = null): JournalEntry
    {
        $existing = JournalEntry::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $this->assertValid($lines);

        try {
            return DB::transaction(function () use ($journal, $sourceType, $sourceId, $description, $lines, $reversalOfId) {
                $entry = JournalEntry::create([
                    'journal' => $journal, 'source_type' => $sourceType, 'source_id' => $sourceId,
                    'description' => $description, 'entry_date' => now()->toDateString(),
                    'reversal_of_id' => $reversalOfId,
                ]);

                $entry->lines()->createMany(array_map(fn (array $l) => [
                    'account_code' => $l['account'],
                    'debit_minor' => $l['debit'] ?? 0,
                    'credit_minor' => $l['credit'] ?? 0,
                ], $lines));

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            return JournalEntry::where('source_type', $sourceType)->where('source_id', $sourceId)->firstOrFail();
        }
    }

    /** @param list<array{account: string, debit?: int, credit?: int}> $lines */
    private function assertValid(array $lines): void
    {
        if (count($lines) < 2) {
            throw UnbalancedEntry::invalid('au moins deux lignes sont requises');
        }

        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            $debit = $line['debit'] ?? 0;
            $credit = $line['credit'] ?? 0;

            if ($debit < 0 || $credit < 0) {
                throw UnbalancedEntry::invalid('montants négatifs interdits');
            }
            if (($debit > 0) === ($credit > 0)) {
                throw UnbalancedEntry::invalid('une ligne est soit au débit, soit au crédit');
            }

            $debits += $debit;
            $credits += $credit;
        }

        if ($debits !== $credits) {
            throw UnbalancedEntry::debitsNotEqualCredits($debits, $credits);
        }
    }
}
