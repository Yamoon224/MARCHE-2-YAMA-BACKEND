<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Invoicing\Models\CreditNote;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Actions\RefundPaymentAction;
use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Events\RefundIssued;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\Event;

it('refunds everything collected, issues a full credit note and clears the ledger when a paid order is cancelled', function () {
    $ctx = awaitingPaymentOrder(2, 5900, 1800); // 11 800 TTC dont 1 800 de TVA
    payOrder($ctx);

    cancelOrder($ctx['orderId'])->assertOk()->assertJsonPath('data.status', 'cancelled');

    $refund = refundsOf($ctx['orderId'])->sole();
    expect($refund->amount_minor)->toBe(11800)
        ->and($refund->status->value)->toBe('succeeded')
        ->and($refund->kind)->toBe(RefundKind::Cancellation)
        ->and($refund->provider_reference)->toStartWith('fake_rf_');

    $note = creditNotesOf($ctx['orderId'])->sole();
    expect($note->number)->toBe(sprintf('AV-%d-000001', now()->year))
        ->and($note->total_minor)->toBe(11800)->and($note->vat_minor)->toBe(1800)->and($note->net_minor)->toBe(10000);

    // Vente annulée et argent rendu : tous les comptes reviennent à zéro, le grand livre reste équilibré.
    foreach (['411', '701', '4431', '5851'] as $account) {
        expect(ledger($account))->toBe(0, "compte {$account}");
    }
    expect(ledgerIsBalanced())->toBeTrue();
});

it('shows the credit on the invoice, which itself stays unchanged', function () {
    $ctx = awaitingPaymentOrder(1, 4000);
    payOrder($ctx);
    cancelOrder($ctx['orderId']);

    $this->actingAs($ctx['customer'], 'sanctum')->getJson('/api/v1/orders/'.orderUuid($ctx['orderId']).'/invoice')
        ->assertOk()
        ->assertJsonPath('data.total_minor', 4000)
        ->assertJsonPath('data.credited_minor', 4000)
        ->assertJsonPath('data.credit_notes.0.total_minor', 4000)
        ->assertJsonPath('data.credit_notes.0.reason', 'Annulation de la commande');
});

it('does nothing for an unpaid order: no refund, no invoice, no credit note, no entry', function () {
    $ctx = awaitingPaymentOrder();

    cancelOrder($ctx['orderId'])->assertOk();

    expect(refundsOf($ctx['orderId']))->toHaveCount(0)->and(creditNotesOf($ctx['orderId']))->toHaveCount(0)
        ->and(DB::table('journal_entries')->count())->toBe(0);
});

it('credits the invoice but refunds nothing for a cash on delivery order never collected', function () {
    $ctx = awaitingPaymentOrder(1, 4000);
    $paymentId = startPayment($ctx['customer'], $ctx['orderId'], 'cash_on_delivery')->json('data.id');
    $this->actingAs(User::factory()->role(Role::OrderOperator)->create(), 'sanctum')
        ->postJson("/api/v1/payments/{$paymentId}/validate-cash-on-delivery")->assertOk();
    expect(ledger('411'))->toBe(4000); // facturé, pas encaissé

    cancelOrder($ctx['orderId'])->assertOk();

    expect(refundsOf($ctx['orderId']))->toHaveCount(0)
        ->and(creditNotesOf($ctx['orderId'])->sole()->total_minor)->toBe(4000)
        ->and(ledger('411'))->toBe(0)->and(ledger('701'))->toBe(0)->and(ledger('571'))->toBe(0);
});

it('refunds only once even if the cancellation event is replayed', function () {
    Event::fake([RefundIssued::class]);
    $ctx = awaitingPaymentOrder(1, 4000);
    payOrder($ctx);
    $action = app(RefundPaymentAction::class);

    $action->execute($ctx['orderId'], 4000, RefundKind::Cancellation, 'test', "cancellation:order:{$ctx['orderId']}", strict: false);
    $action->execute($ctx['orderId'], 4000, RefundKind::Cancellation, 'test', "cancellation:order:{$ctx['orderId']}", strict: false);

    expect(refundsOf($ctx['orderId']))->toHaveCount(1);
    Event::assertDispatchedTimes(RefundIssued::class, 1);
});

it('caps an automatic refund at what the customer actually paid', function () {
    $ctx = awaitingPaymentOrder(1, 4000);
    payOrder($ctx);

    $refunds = app(RefundPaymentAction::class)->execute($ctx['orderId'], 999_999, RefundKind::Adjustment, 'test', 'cap-test', strict: false);

    expect(collect($refunds)->sum('amount_minor'))->toBe(4000);
});

it('returns money paid too late for a cancelled order, with no invoice and a neutral ledger', function () {
    $ctx = awaitingPaymentOrder(1, 4000);
    $paymentId = startPayment($ctx['customer'], $ctx['orderId'])->json('data.id');
    app(TransitionOrderAction::class)->execute(Order::findOrFail($ctx['orderId']), OrderStatus::Cancelled, null, 'Expirée');

    sendWebhook(webhookFor(Payment::findOrFail($paymentId)->provider_reference, 'succeeded', 4000, 'evt-late'))->assertOk();

    $refund = refundsOf($ctx['orderId'])->sole();
    expect($refund->kind)->toBe(RefundKind::LatePayment)->and($refund->amount_minor)->toBe(4000)->and($refund->status->value)->toBe('succeeded')
        ->and(creditNotesOf($ctx['orderId']))->toHaveCount(0)
        ->and(ledger('5851'))->toBe(0)->and(ledger('411'))->toBe(0)->and(ledgerIsBalanced())->toBeTrue();
});

it('never credits more than was invoiced across several corrections', function () {
    $ctx = awaitingPaymentOrder(1, 10_000);
    payOrder($ctx);
    $staff = User::factory()->role(Role::Support)->create();

    $this->actingAs($staff, 'sanctum')->postJson('/api/v1/orders/'.orderUuid($ctx['orderId']).'/refunds', ['amount_minor' => 3000, 'reason' => 'Geste commercial'])->assertCreated();
    cancelOrder($ctx['orderId'])->assertOk();

    expect(creditNotesOf($ctx['orderId'])->sum('total_minor'))->toBe(10_000)
        ->and(refundsOf($ctx['orderId'])->sum('amount_minor'))->toBe(10_000)
        ->and(ledger('411'))->toBe(0)->and(ledger('701'))->toBe(0)->and(ledger('5851'))->toBe(0);
});

it('numbers credit notes sequentially and keeps them immutable', function () {
    foreach (['evt-a', 'evt-b'] as $event) {
        $ctx = awaitingPaymentOrder(1, 1000);
        payOrder($ctx, $event);
        cancelOrder($ctx['orderId']);
    }

    expect(CreditNote::orderBy('id')->pluck('number')->all())->toBe([
        sprintf('AV-%d-000001', now()->year), sprintf('AV-%d-000002', now()->year),
    ]);

    $note = CreditNote::firstOrFail();
    expect(fn () => $note->update(['total_minor' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $note->delete())->toThrow(LogicException::class);
});

it('keeps the ledger balanced across a mix of paid, cancelled and untouched orders', function () {
    $keep = awaitingPaymentOrder(2, 1500, 1800);
    payOrder($keep, 'evt-keep');
    $drop = awaitingPaymentOrder(3, 2300, 1800);
    payOrder($drop, 'evt-drop');
    awaitingPaymentOrder(); // jamais payée

    cancelOrder($drop['orderId']);

    expect(ledgerIsBalanced())->toBeTrue()
        ->and(ledger('5851'))->toBe($keep['total'])   // seul l'argent de la commande conservée reste encaissé
        ->and(ledger('411'))->toBe(0);
});
