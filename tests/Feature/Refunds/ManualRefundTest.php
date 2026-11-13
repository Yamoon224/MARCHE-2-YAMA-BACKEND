<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Payments\Actions\RetryFailedRefundsAction;
use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Events\RefundIssued;
use Illuminate\Support\Facades\Event;

function refund(int $orderId, array $body, ?User $as = null, array $headers = [])
{
    return test()->actingAs($as ?? User::factory()->role(Role::Support)->create(), 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/refunds", $body, $headers);
}

beforeEach(function () {
    $this->ctx = awaitingPaymentOrder(1, 10_000);
    payOrder($this->ctx);
    $this->orderId = $this->ctx['orderId'];
});

it('lets support refund part of a paid order, with a credit note and ledger entries', function () {
    refund($this->orderId, ['amount_minor' => 2500, 'reason' => 'Produit abîmé'])
        ->assertCreated()
        ->assertJsonPath('data.0.amount_minor', 2500)
        ->assertJsonPath('data.0.kind', 'manual')
        ->assertJsonPath('data.0.status', 'succeeded');

    expect(creditNotesOf($this->orderId)->sole()->total_minor)->toBe(2500)
        ->and(ledger('5851'))->toBe(7500)->and(ledger('411'))->toBe(0)->and(ledger('701'))->toBe(-7500)
        ->and(ledgerIsBalanced())->toBeTrue();
});

it('is limited to authorised roles', function () {
    $body = ['amount_minor' => 1000, 'reason' => 'Geste commercial'];

    asGuest();
    $this->postJson("/api/v1/orders/{$this->orderId}/refunds", $body)->assertUnauthorized();
    refund($this->orderId, $body, $this->ctx['customer'])->assertForbidden();
    refund($this->orderId, $body, User::factory()->role(Role::Courier)->create())->assertForbidden();
    refund($this->orderId, $body, User::factory()->role(Role::Accountant)->create())->assertForbidden();
    refund($this->orderId, $body, User::factory()->role(Role::Picker)->create())->assertForbidden();

    expect(refundsOf($this->orderId))->toHaveCount(0);

    refund($this->orderId, $body, User::factory()->role(Role::Admin)->create())->assertCreated();
});

it('refuses an amount above what is refundable, cumulatively', function () {
    refund($this->orderId, ['amount_minor' => 6000, 'reason' => 'Premier geste'])->assertCreated();

    refund($this->orderId, ['amount_minor' => 4001, 'reason' => 'Trop'])->assertUnprocessable()->assertJsonValidationErrors('amount_minor');
    refund($this->orderId, ['amount_minor' => 4000, 'reason' => 'Le solde'])->assertCreated();
    refund($this->orderId, ['amount_minor' => 1, 'reason' => 'Plus rien'])->assertUnprocessable();

    expect(refundsOf($this->orderId)->sum('amount_minor'))->toBe(10_000);
});

it('refuses to refund an order with nothing collected', function () {
    $unpaid = awaitingPaymentOrder();

    refund($unpaid['orderId'], ['amount_minor' => 100, 'reason' => 'Test'])
        ->assertUnprocessable()->assertJsonPath('errors.amount_minor.0', 'Aucun paiement encaissé à rembourser pour cette commande.');
});

it('answers 404 for an unknown order', function () {
    refund(999_999, ['amount_minor' => 100, 'reason' => 'Test'])->assertNotFound();
});

it('validates the payload', function (array $body) {
    refund($this->orderId, $body)->assertUnprocessable();
})->with([
    'no amount' => [['reason' => 'Test']],
    'zero' => [['amount_minor' => 0, 'reason' => 'Test']],
    'negative' => [['amount_minor' => -5, 'reason' => 'Test']],
    'fraction' => [['amount_minor' => 10.5, 'reason' => 'Test']],
    'no reason' => [['amount_minor' => 100]],
    'blank reason' => [['amount_minor' => 100, 'reason' => '  ']],
]);

it('refunds once per Idempotency-Key and again without one', function () {
    $support = User::factory()->role(Role::Support)->create();
    $body = ['amount_minor' => 1000, 'reason' => 'Geste commercial'];

    refund($this->orderId, $body, $support, ['Idempotency-Key' => 'k1'])->assertCreated();
    refund($this->orderId, $body, $support, ['Idempotency-Key' => 'k1'])->assertCreated();
    expect(refundsOf($this->orderId))->toHaveCount(1);

    refund($this->orderId, $body, $support)->assertCreated();
    expect(refundsOf($this->orderId))->toHaveCount(2);
});

it('tells how much is still refundable', function () {
    $support = User::factory()->role(Role::Support)->create();
    $url = "/api/v1/orders/{$this->orderId}/refunds";

    $this->actingAs($support, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('meta.refundable_minor', 10_000);

    refund($this->orderId, ['amount_minor' => 4000, 'reason' => 'Geste commercial'], $support)->assertCreated();
    $this->actingAs($support, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('meta.refundable_minor', 6000);

    // Un remboursement refusé par l'agrégateur ne réduit pas le remboursable.
    config(['payments.gateways.fake.fail_refunds' => true]);
    refund($this->orderId, ['amount_minor' => 1000, 'reason' => 'Refusé'], $support)->assertCreated();
    $this->actingAs($support, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('meta.refundable_minor', 6000);

    // Une commande non payée n'a rien de remboursable.
    $unpaid = awaitingPaymentOrder();
    $this->actingAs($support, 'sanctum')->getJson("/api/v1/orders/{$unpaid['orderId']}/refunds")->assertOk()->assertJsonPath('meta.refundable_minor', 0);
});

it('lists the refunds of an order to its owner and to staff only', function () {
    refund($this->orderId, ['amount_minor' => 1000, 'reason' => 'Geste commercial'])->assertCreated();
    $url = "/api/v1/orders/{$this->orderId}/refunds";

    asGuest();
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs($this->ctx['customer'], 'sanctum')->getJson($url)->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs(User::factory()->role(Role::Support)->create(), 'sanctum')->getJson($url)->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertNotFound();
    $this->actingAs(User::factory()->role(Role::Courier)->create(), 'sanctum')->getJson($url)->assertNotFound();
});

// ───────────────────────── échec de l'agrégateur ─────────────────────────

it('keeps a refund refused by the gateway for retry, without booking anything', function () {
    Event::fake([RefundIssued::class]);
    config(['payments.gateways.fake.fail_refunds' => true]);

    refund($this->orderId, ['amount_minor' => 4000, 'reason' => 'Geste commercial'])
        ->assertCreated()->assertJsonPath('data.0.status', 'failed');

    Event::assertNotDispatched(RefundIssued::class);
    expect(refundsOf($this->orderId)->sole()->failure)->toContain('Remboursement refusé')
        ->and(DB::table('journal_entries')->where('source_type', 'refund')->count())->toBe(0);
});

it('does not count a failed refund as refunded, so the amount can be refunded again', function () {
    config(['payments.gateways.fake.fail_refunds' => true]);
    refund($this->orderId, ['amount_minor' => 10_000, 'reason' => 'Tout'])->assertCreated();

    config(['payments.gateways.fake.fail_refunds' => false]);
    refund($this->orderId, ['amount_minor' => 10_000, 'reason' => 'Tout, de nouveau'])->assertCreated()->assertJsonPath('data.0.status', 'succeeded');
});

it('retries failed refunds once the gateway is back, then books them', function () {
    config(['payments.gateways.fake.fail_refunds' => true]);
    cancelOrder($this->orderId)->assertOk();
    expect(refundsOf($this->orderId)->sole()->status->value)->toBe('failed')
        ->and(creditNotesOf($this->orderId)->sole()->total_minor)->toBe(10_000)
        ->and(ledger('411'))->toBe(-10_000); // l'avoir est passé, l'argent n'est pas encore rendu : dette envers le client

    config(['payments.gateways.fake.fail_refunds' => false]);
    $this->artisan('payments:retry-refunds')->expectsOutputToContain('1 remboursement(s)')->assertSuccessful();

    expect(refundsOf($this->orderId)->sole()->status->value)->toBe('succeeded')
        ->and(ledger('411'))->toBe(0)->and(ledger('5851'))->toBe(0)->and(ledgerIsBalanced())->toBeTrue();
});

it('keeps failing refunds in place while the gateway is down, and retrying twice never double-refunds', function () {
    config(['payments.gateways.fake.fail_refunds' => true]);
    cancelOrder($this->orderId)->assertOk();

    expect(app(RetryFailedRefundsAction::class)->execute())->toBe(0);
    expect(refundsOf($this->orderId)->sole()->status->value)->toBe('failed');

    config(['payments.gateways.fake.fail_refunds' => false]);
    app(RetryFailedRefundsAction::class)->execute();
    app(RetryFailedRefundsAction::class)->execute();

    expect(refundsOf($this->orderId))->toHaveCount(1)
        ->and(DB::table('journal_entries')->where('source_type', 'refund')->count())->toBe(1);
});

it('records the right kind for every origin of refund', function () {
    refund($this->orderId, ['amount_minor' => 500, 'reason' => 'Geste commercial'])->assertCreated();
    cancelOrder($this->orderId)->assertOk();

    expect(refundsOf($this->orderId)->pluck('kind')->map->value->all())->toBe([RefundKind::Manual->value, RefundKind::Cancellation->value]);
});
