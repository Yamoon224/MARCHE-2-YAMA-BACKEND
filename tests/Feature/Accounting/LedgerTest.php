<?php

use App\Modules\Accounting\Actions\PostEntryAction;
use App\Modules\Accounting\Actions\ReverseEntryAction;
use App\Modules\Accounting\Exceptions\UnbalancedEntry;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function post(array $lines, int $sourceId = 1, string $type = 'test'): JournalEntry
{
    return app(PostEntryAction::class)->execute('OD', $type, $sourceId, 'Test', $lines);
}

it('posts a balanced entry', function () {
    $entry = post([['account' => '411', 'debit' => 1000], ['account' => '701', 'credit' => 1000]]);

    expect($entry->lines)->toHaveCount(2)
        ->and($entry->lines->sum('debit_minor'))->toBe($entry->lines->sum('credit_minor'));
});

it('refuses unbalanced or malformed entries', function (array $lines) {
    expect(fn () => post($lines))->toThrow(UnbalancedEntry::class)
        ->and(JournalEntry::count())->toBe(0);
})->with([
    'debit ≠ credit' => [[['account' => '411', 'debit' => 1000], ['account' => '701', 'credit' => 999]]],
    'single line' => [[['account' => '411', 'debit' => 0]]],
    'both sides on one line' => [[['account' => '411', 'debit' => 5, 'credit' => 5], ['account' => '701', 'credit' => 0]]],
    'zero line' => [[['account' => '411'], ['account' => '701', 'credit' => 0]]],
    'negative' => [[['account' => '411', 'debit' => -5], ['account' => '701', 'credit' => -5]]],
]);

it('refuses an unknown account', function () {
    expect(fn () => post([['account' => '999', 'debit' => 10], ['account' => '701', 'credit' => 10]]))
        ->toThrow(QueryException::class);

    expect(JournalEntry::count())->toBe(0)->and(JournalLine::count())->toBe(0);
});

it('is idempotent per source', function () {
    $first = post([['account' => '411', 'debit' => 1000], ['account' => '701', 'credit' => 1000]], 7, 'invoice');
    $second = post([['account' => '411', 'debit' => 1000], ['account' => '701', 'credit' => 1000]], 7, 'invoice');

    expect($second->id)->toBe($first->id)->and(JournalEntry::count())->toBe(1)->and(JournalLine::count())->toBe(2);
});

it('is append-only for the application', function () {
    $entry = post([['account' => '411', 'debit' => 1000], ['account' => '701', 'credit' => 1000]]);

    expect(fn () => $entry->update(['description' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(fn () => $entry->lines->first()->update(['debit_minor' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $entry->lines->first()->delete())->toThrow(LogicException::class);
});

it('is append-only for the database itself, even with raw queries', function () {
    post([['account' => '411', 'debit' => 1000], ['account' => '701', 'credit' => 1000]]);

    expect(fn () => DB::table('journal_lines')->update(['debit_minor' => 1]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('journal_lines')->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('journal_entries')->update(['description' => 'x']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('journal_entries')->delete())->toThrow(QueryException::class);

    expect((int) DB::table('journal_lines')->sum('debit_minor'))->toBe(1000);
})->skip(fn () => DB::getDriverName() !== 'mysql', 'Triggers MySQL uniquement');

it('corrects by reversal, leaving the original untouched', function () {
    $original = post([['account' => '411', 'debit' => 1000], ['account' => '701', 'credit' => 1000]]);

    $reversal = app(ReverseEntryAction::class)->execute($original->load('lines'), 'Erreur de saisie');
    app(ReverseEntryAction::class)->execute($original, 'rejeu');

    expect($reversal->reversal_of_id)->toBe($original->id)
        ->and(JournalEntry::count())->toBe(2)
        ->and((int) JournalLine::where('account_code', '411')->sum('debit_minor') - (int) JournalLine::where('account_code', '411')->sum('credit_minor'))->toBe(0)
        ->and($original->refresh()->description)->toBe('Test');
});
