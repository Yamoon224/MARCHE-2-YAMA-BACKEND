<?php

use App\Modules\Claims\Models\Claim;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Actions\RefundPaymentAction;
use App\Modules\Payments\Enums\RefundKind;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->milk = pieceProduct(1000, 50, 'Lait');
    $this->ctx = deliveredOrder([['product' => $this->milk, 'quantity' => 4], ['product' => pieceProduct(2500, 50, 'Riz'), 'quantity' => 2]]);
    $this->order = $this->ctx['order'];
    $this->lines = array_values($this->ctx['lines']);
    // 4 000 + 5 000 = 9 000 payés ; on réclame 2 laits abîmés (plafond 2 000).
    $this->claimId = openClaim($this->ctx['customer'], $this->order->id, [claimItem($this->lines[0], 'damaged', 2)], [photo()])->assertCreated()->json('data.id');
});

it('accepts a claim with a refund: money back, credit note, order refunded, ledger consistent', function () {
    decideClaim($this->claimId, ['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'supplier', 'amount_minor' => 1500, 'note' => 'Briques percées, photo claire'])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.resolution', 'refund')
        ->assertJsonPath('data.cost_bearer', 'supplier')
        ->assertJsonPath('data.refund_amount_minor', 1500)
        ->assertJsonPath('data.refund_completed', true)
        ->assertJsonPath('data.decision_note', 'Briques percées, photo claire');

    $refund = refundsOf($this->order->id)->sole();
    expect($refund->kind->value)->toBe('claim')->and($refund->amount_minor)->toBe(1500)->and($refund->status->value)->toBe('succeeded')
        ->and(Order::find($this->order->id)->status)->toBe(OrderStatus::Refunded)
        ->and(creditNotesOf($this->order->id)->sole()->total_minor)->toBe(1500)
        ->and(creditNotesOf($this->order->id)->sole()->reason)->toBe('Réclamation acceptée')
        ->and(ledger('411'))->toBe(0)->and(ledger('5851'))->toBe(7500)->and(ledgerIsBalanced())->toBeTrue();
});

it('records who decided and when', function () {
    $agent = supportAgent();
    decideClaim($this->claimId, ['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'store', 'amount_minor' => 1000, 'note' => 'Geste'], $agent)->assertOk();

    $claim = Claim::find($this->claimId);
    expect($claim->decided_by)->toBe($agent->id)->and($claim->decided_at)->not->toBeNull();
});

it('refuses a refund above the value of what was claimed', function () {
    decideClaim($this->claimId, ['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'supplier', 'amount_minor' => 2001, 'note' => 'Trop'])
        ->assertUnprocessable()->assertJsonPath('errors.claim.0', 'Le remboursement ne peut pas dépasser la valeur des articles réclamés (2000).');

    expect(Claim::find($this->claimId)->status->value)->toBe('open')->and(Order::find($this->order->id)->status)->toBe(OrderStatus::Claim)->and(refundsOf($this->order->id))->toHaveCount(0);
});

it('refuses a refund above what was actually collected and not yet refunded, changing nothing', function () {
    // 8 500 déjà rendus par ailleurs : il ne reste que 500 de remboursable.
    app(RefundPaymentAction::class)->execute($this->order->id, 8500, RefundKind::Manual, 'Autre geste', 'other-gesture');

    decideClaim($this->claimId, ['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'supplier', 'amount_minor' => 1500, 'note' => 'Briques percées'])
        ->assertUnprocessable()->assertJsonValidationErrors('claim');

    expect(Claim::find($this->claimId)->status->value)->toBe('open')->and(Order::find($this->order->id)->status)->toBe(OrderStatus::Claim);
});

it('accepts a claim with a new shipment: no money moves and the cost is attributed', function () {
    decideClaim($this->claimId, ['decision' => 'accept', 'resolution' => 'redelivery', 'cost_bearer' => 'preparation', 'note' => 'On renvoie 2 briques'])
        ->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.resolution', 'redelivery')->assertJsonPath('data.refund_amount_minor', 0);

    expect(refundsOf($this->order->id))->toHaveCount(0)->and(Order::find($this->order->id)->status)->toBe(OrderStatus::Completed);
});

it('rejects a claim with a mandatory reason, without any refund', function () {
    decideClaim($this->claimId, ['decision' => 'reject', 'note' => ''])->assertUnprocessable()->assertJsonValidationErrors('note');
    decideClaim($this->claimId, ['decision' => 'reject', 'note' => 'La photo montre un autre produit'])
        ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.resolution', null)->assertJsonPath('data.cost_bearer', null);

    expect(refundsOf($this->order->id))->toHaveCount(0)->and(Order::find($this->order->id)->status)->toBe(OrderStatus::Completed);
});

it('can be decided only once', function () {
    decideClaim($this->claimId, ['decision' => 'reject', 'note' => 'Refusée'])->assertOk();

    decideClaim($this->claimId, ['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'supplier', 'amount_minor' => 1000, 'note' => 'Finalement si'])
        ->assertUnprocessable()->assertJsonPath('errors.claim.0', 'Cette réclamation a déjà fait l\'objet d\'une décision.');
    expect(refundsOf($this->order->id))->toHaveCount(0);
});

it('does not refund twice when the same decision is submitted again', function () {
    $body = ['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'supplier', 'amount_minor' => 1500, 'note' => 'Accepté'];

    decideClaim($this->claimId, $body)->assertOk();
    decideClaim($this->claimId, $body)->assertUnprocessable();

    expect(refundsOf($this->order->id))->toHaveCount(1)->and(creditNotesOf($this->order->id))->toHaveCount(1);
});

it('validates the decision payload', function (array $body, string $field) {
    decideClaim($this->claimId, $body)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Claim::find($this->claimId)->status->value)->toBe('open');
})->with([
    'unknown decision' => [['decision' => 'maybe', 'note' => 'abc'], 'decision'],
    'no note' => [['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'store', 'amount_minor' => 100], 'note'],
    'accept without resolution' => [['decision' => 'accept', 'cost_bearer' => 'store', 'note' => 'abc'], 'resolution'],
    'accept without cost bearer' => [['decision' => 'accept', 'resolution' => 'redelivery', 'note' => 'abc'], 'cost_bearer'],
    'refund without amount' => [['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'store', 'note' => 'abc'], 'amount_minor'],
    'bad cost bearer' => [['decision' => 'accept', 'resolution' => 'redelivery', 'cost_bearer' => 'the moon', 'note' => 'abc'], 'cost_bearer'],
    'fractional amount' => [['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'store', 'amount_minor' => 10.5, 'note' => 'abc'], 'amount_minor'],
]);

it('is reserved to staff allowed to decide claims', function () {
    $body = ['decision' => 'reject', 'note' => 'Refusée'];

    asGuest();
    $this->postJson("/api/v1/claims/{$this->claimId}/decision", $body)->assertUnauthorized();
    foreach ([$this->ctx['customer'], User::factory()->role(Role::Courier)->create(), User::factory()->role(Role::Picker)->create(), User::factory()->role(Role::Accountant)->create()] as $user) {
        decideClaim($this->claimId, $body, $user)->assertForbidden();
    }

    expect(Claim::find($this->claimId)->status->value)->toBe('open');
    decideClaim($this->claimId, $body, User::factory()->role(Role::Admin)->create())->assertOk();
});

it('keeps the decision when the gateway refuses the refund, and completes it once the gateway is back', function () {
    config(['payments.gateways.fake.fail_refunds' => true]);

    decideClaim($this->claimId, ['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'supplier', 'amount_minor' => 1500, 'note' => 'Accepté'])
        ->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.refund_completed', false);

    expect(refundsOf($this->order->id)->sole()->status->value)->toBe('failed')->and(Order::find($this->order->id)->status)->toBe(OrderStatus::Refunded);

    config(['payments.gateways.fake.fail_refunds' => false]);
    $this->artisan('payments:retry-refunds')->assertSuccessful();

    expect(refundsOf($this->order->id)->sole()->status->value)->toBe('succeeded')
        ->and(Claim::find($this->claimId)->refund_completed)->toBeTrue() // la réclamation est avertie du remboursement tardif
        ->and(ledger('411'))->toBe(0)->and(ledgerIsBalanced())->toBeTrue();
});

// ───────────────────────── consultation ─────────────────────────

it('shows a claim only to its author and to staff', function () {
    $url = "/api/v1/claims/{$this->claimId}";

    asGuest();
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs($this->ctx['customer'], 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.items.0.name', 'Lait');
    $this->actingAs(supportAgent(), 'sanctum')->getJson($url)->assertOk();
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertNotFound();
    $this->actingAs(User::factory()->role(Role::Courier)->create(), 'sanctum')->getJson($url)->assertNotFound();
});

it('lists a customer\'s own claims and every claim for staff, filtered by status', function () {
    $other = deliveredOrder([['product' => pieceProduct(), 'quantity' => 1]]);
    openClaim($other['customer'], $other['order']->id, [claimItem(array_values($other['lines'])[0], 'missing')])->assertCreated();
    decideClaim($this->claimId, ['decision' => 'reject', 'note' => 'Refusée'])->assertOk();

    $this->actingAs($this->ctx['customer'], 'sanctum')->getJson('/api/v1/claims')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->claimId);
    $this->actingAs(supportAgent(), 'sanctum')->getJson('/api/v1/claims')->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs(supportAgent(), 'sanctum')->getJson('/api/v1/claims?status=open')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs(supportAgent(), 'sanctum')->getJson('/api/v1/claims?status=rejected')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->claimId);
});

it('serves photos only to the author and to staff, as private images', function () {
    $photoUrl = Claim::find($this->claimId)->photos()->firstOrFail();
    $url = "/api/v1/claims/{$this->claimId}/photos/{$photoUrl->id}";

    asGuest();
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'sanctum')->get($url)->assertNotFound();

    $response = $this->actingAs($this->ctx['customer'], 'sanctum')->get($url)->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('image/')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('private');
    $this->actingAs(supportAgent(), 'sanctum')->get($url)->assertOk();
});

it('does not let a photo of another claim be fetched through this one', function () {
    $other = deliveredOrder([['product' => pieceProduct(), 'quantity' => 1]]);
    $otherClaim = openClaim($other['customer'], $other['order']->id, [claimItem(array_values($other['lines'])[0], 'damaged')], [photo()])->assertCreated()->json('data.id');
    $foreignPhoto = Claim::find($otherClaim)->photos()->firstOrFail();

    $this->actingAs($this->ctx['customer'], 'sanctum')->get("/api/v1/claims/{$this->claimId}/photos/{$foreignPhoto->id}")->assertNotFound();
});

// ───────────────────────── statistiques de qualité ─────────────────────────

it('gives quality statistics to staff only', function () {
    decideClaim($this->claimId, ['decision' => 'accept', 'resolution' => 'refund', 'cost_bearer' => 'supplier', 'amount_minor' => 1500, 'note' => 'Accepté'])->assertOk();
    $other = deliveredOrder([['product' => $this->milk, 'quantity' => 2]]); // même produit : regroupé dans les statistiques
    openClaim($other['customer'], $other['order']->id, [claimItem(array_values($other['lines'])[0], 'missing', 1)])->assertCreated();

    asGuest();
    $this->getJson('/api/v1/claims/quality-stats')->assertUnauthorized();
    $this->actingAs($this->ctx['customer'], 'sanctum')->getJson('/api/v1/claims/quality-stats')->assertForbidden();

    $stats = $this->actingAs(supportAgent(), 'sanctum')->getJson('/api/v1/claims/quality-stats')->assertOk();
    expect($stats->json('claims_total'))->toBe(2)->and($stats->json('accepted'))->toBe(1)->and($stats->json('open'))->toBe(1)->and($stats->json('refunded_minor'))->toBe(1500)
        ->and($stats->json('by_cost_bearer'))->toBe([['cost_bearer' => 'supplier', 'claims' => 1, 'refunded_minor' => 1500]])
        ->and(collect($stats->json('by_issue'))->pluck('items', 'issue')->all())->toBe(['damaged' => 1, 'missing' => 1])
        ->and($stats->json('by_product.0.name'))->toBe('Lait')->and($stats->json('by_product.0.claims'))->toBe(2);
});
