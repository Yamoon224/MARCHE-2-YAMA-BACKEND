<?php

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

/** Solde (débit - crédit) d'un compte. */
function balance(string $account): int
{
    return (int) JournalLine::where('account_code', $account)->sum('debit_minor')
        - (int) JournalLine::where('account_code', $account)->sum('credit_minor');
}

it('books the sale and the receipt of a paid order', function () {
    $ctx = awaitingPaymentOrder(2, 5900, 1800); // 11 800 TTC : 10 000 HT + 1 800 TVA
    payOrder($ctx);

    expect(balance('701'))->toBe(-10000)     // ventes au crédit
        ->and(balance('4431'))->toBe(-1800)  // TVA collectée au crédit
        ->and(balance('5851'))->toBe(11800)  // mobile money encaissé au débit
        ->and(balance('411'))->toBe(0)       // client soldé : facturé = encaissé
        ->and(JournalEntry::count())->toBe(2);
});

it('keeps the ledger balanced whatever happens', function () {
    foreach (range(1, 5) as $i) {
        payOrder(awaitingPaymentOrder($i, 1000 + $i * 37, $i % 2 ? 1800 : 0), "evt-{$i}");
    }
    awaitingPaymentOrder(); // commande non payée : aucune écriture

    $debits = (int) DB::table('journal_lines')->sum('debit_minor');
    $credits = (int) DB::table('journal_lines')->sum('credit_minor');

    expect($debits)->toBe($credits)->and($debits)->toBeGreaterThan(0)
        ->and(balance('411'))->toBe(0)
        ->and(JournalEntry::count())->toBe(10);
});

it('does not book anything twice when the payment webhook is replayed', function () {
    $ctx = awaitingPaymentOrder();
    $paymentId = payOrder($ctx);
    $providerRef = Payment::findOrFail($paymentId)->provider_reference;

    sendWebhook(webhookFor($providerRef, 'succeeded', $ctx['total'], 'evt-pay'))->assertOk()->assertJsonPath('outcome', 'duplicate');
    sendWebhook(webhookFor($providerRef, 'succeeded', $ctx['total'], 'evt-pay-2'))->assertOk()->assertJsonPath('outcome', 'ignored');

    expect(JournalEntry::count())->toBe(2)->and(balance('5851'))->toBe($ctx['total']);
});

it('books the sale but no receipt for a validated cash on delivery order', function () {
    $ctx = awaitingPaymentOrder(1, 4000);
    $paymentId = startPayment($ctx['customer'], $ctx['orderId'], 'cash_on_delivery')->json('data.id');

    $this->actingAs(User::factory()->role(Role::OrderOperator)->create(), 'sanctum')
        ->postJson("/api/v1/payments/{$paymentId}/validate-cash-on-delivery")->assertOk();

    // Facture émise (commande confirmée) mais l'argent reste à encaisser : créance client ouverte.
    expect(balance('411'))->toBe(4000)->and(balance('701'))->toBe(-4000)->and(balance('571'))->toBe(0);
});

it('does not book a flagged payment as received', function () {
    $ctx = awaitingPaymentOrder();
    $paymentId = startPayment($ctx['customer'], $ctx['orderId'])->json('data.id');
    $providerRef = Payment::findOrFail($paymentId)->provider_reference;

    sendWebhook(webhookFor($providerRef, 'succeeded', $ctx['total'] - 1, 'evt-flag'))->assertOk()->assertJsonPath('outcome', 'flagged');

    expect(JournalEntry::count())->toBe(0);
});

it('exposes a trial balance to accountants only', function () {
    payOrder(awaitingPaymentOrder(2, 5900, 1800));

    asGuest();
    $this->getJson('/api/v1/accounting/trial-balance')->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/accounting/trial-balance')->assertForbidden();
    $this->actingAs(User::factory()->role(Role::Support)->create(), 'sanctum')->getJson('/api/v1/accounting/trial-balance')->assertForbidden();

    $response = $this->actingAs(User::factory()->role(Role::Accountant)->create(), 'sanctum')
        ->getJson('/api/v1/accounting/trial-balance')->assertOk()
        ->assertJsonPath('balanced', true)
        ->assertJsonPath('total_debit_minor', 23600)
        ->assertJsonPath('total_credit_minor', 23600);

    $byCode = collect($response->json('accounts'))->keyBy('code');
    expect($byCode['411']['balance_minor'])->toBe(0)
        ->and($byCode['701']['balance_minor'])->toBe(-10000)
        ->and($byCode['5851']['balance_minor'])->toBe(11800);
});
