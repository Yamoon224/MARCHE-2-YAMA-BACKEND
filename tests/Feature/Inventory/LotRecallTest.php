<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\LotRecall;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Orders\Enums\OrderStatus;

function stockKeeper(): User
{
    return User::factory()->role(Role::StockKeeper)->create();
}

function recall(StockLot $lot, string $reason = 'Listeria détectée chez le fournisseur', ?User $as = null)
{
    return test()->actingAs($as ?? stockKeeper(), 'sanctum')->postJson("/api/v1/inventory/lots/{$lot->id}/recall", ['reason' => $reason]);
}

beforeEach(function () {
    $this->milk = pieceProduct(1000, 0, 'Lait');
    StockLot::where('product_id', $this->milk->id)->delete(); // pieceProduct() crée un lot vide : on part d'un stock net
    $this->lot = stockUp($this->milk, 20, now()->addDays(5)->toDateString());
});

it('blocks a recalled lot at once and records the decision', function () {
    $keeper = stockKeeper();

    recall($this->lot, 'Listeria détectée', $keeper)
        ->assertOk()->assertJsonPath('lot.is_blocked', true)->assertJsonPath('lot.recalled', true)->assertJsonPath('reason', 'Listeria détectée');

    $recall = LotRecall::where('stock_lot_id', $this->lot->id)->sole();
    expect($this->lot->refresh()->is_blocked)->toBeTrue()->and($recall->recalled_by)->toBe($keeper->id);
});

it('never allocates a recalled lot to a new order', function () {
    recall($this->lot)->assertOk();

    $customer = User::factory()->create();
    $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', ['items' => [['product_id' => $this->milk->id, 'quantity' => 1]]])
        ->assertUnprocessable()->assertJsonValidationErrors('items');

    expect($this->lot->refresh()->quantity_reserved)->toBe(0);
});

it('serves the next lot instead, when another valid one exists', function () {
    $other = stockUp($this->milk, 10, now()->addDays(30)->toDateString());
    recall($this->lot)->assertOk();

    $customer = User::factory()->create();
    $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', ['items' => [['product_id' => $this->milk->id, 'quantity' => 2]]])->assertCreated();

    expect($other->refresh()->quantity_reserved)->toBe(2)->and($this->lot->refresh()->quantity_reserved)->toBe(0);
});

it('identifies the customers who already received the lot, and the orders still reserving it', function () {
    $delivered = deliveredOrder([['product' => $this->milk, 'quantity' => 3]]);   // livrée : à prévenir
    $other = deliveredOrder([['product' => $this->milk, 'quantity' => 2]]);       // livrée aussi
    $pending = awaitingPaymentOrderFor($this->milk, 4);                           // réservée, pas encore livrée : à réallouer

    $report = recall($this->lot)->assertOk()->json();

    expect($report['delivered'])->toBe([
        ['order_id' => $delivered['order']->id, 'customer_id' => $delivered['customer']->id, 'quantity' => 3],
        ['order_id' => $other['order']->id, 'customer_id' => $other['customer']->id, 'quantity' => 2],
    ])->and($report['reserved'])->toBe([
        ['order_id' => $pending['order_id'], 'customer_id' => $pending['customer']->id, 'quantity' => 4],
    ]);
});

it('does not mix up another lot of the same product', function () {
    $safeLot = stockUp($this->milk, 10, now()->addDays(40)->toDateString());
    $ctx = deliveredOrder([['product' => $this->milk, 'quantity' => 2]]); // FEFO : sort du lot qui périme en premier (celui du rappel)

    $report = recall($safeLot)->assertOk()->json();

    expect($report['delivered'])->toBe([])->and($report['reserved'])->toBe([])->and($ctx['order']->id)->toBeInt();
});

it('is idempotent: recalling twice keeps the first reason and creates a single recall', function () {
    recall($this->lot, 'Premier motif')->assertOk();
    recall($this->lot, 'Second motif')->assertOk()->assertJsonPath('reason', 'Premier motif');

    expect(LotRecall::count())->toBe(1);
});

it('requires a reason', function () {
    recall($this->lot, '')->assertUnprocessable()->assertJsonValidationErrors('reason');
    recall($this->lot, 'ab')->assertUnprocessable();

    expect($this->lot->refresh()->is_blocked)->toBeFalse();
});

it('can be consulted again later with the same report', function () {
    $ctx = deliveredOrder([['product' => $this->milk, 'quantity' => 1]]);
    recall($this->lot)->assertOk();

    $this->actingAs(stockKeeper(), 'sanctum')->getJson("/api/v1/inventory/lots/{$this->lot->id}/recall")
        ->assertOk()->assertJsonPath('delivered.0.order_id', $ctx['order']->id)->assertJsonPath('delivered.0.customer_id', $ctx['customer']->id);
});

it('has no report for a lot that was never recalled', function () {
    $this->actingAs(stockKeeper(), 'sanctum')->getJson("/api/v1/inventory/lots/{$this->lot->id}/recall")->assertNotFound();
});

it('keeps customer identifiers away from anyone who does not manage the stock', function () {
    recall($this->lot)->assertOk();
    $url = "/api/v1/inventory/lots/{$this->lot->id}/recall";

    asGuest();
    $this->getJson($url)->assertUnauthorized();
    foreach ([User::factory()->create(), User::factory()->role(Role::Picker)->create(), User::factory()->role(Role::Support)->create(), User::factory()->role(Role::Courier)->create()] as $user) {
        $this->actingAs($user, 'sanctum')->getJson($url)->assertForbidden();
        recall($this->lot, 'Tentative', $user)->assertForbidden();
    }

    $this->actingAs(User::factory()->role(Role::Admin)->create(), 'sanctum')->getJson($url)->assertOk();
});

it('lists lots with their availability, blocked state and expiry filter', function () {
    $soon = stockUp($this->milk, 5, now()->addDays(2)->toDateString());
    recall($this->lot)->assertOk();
    $viewer = User::factory()->role(Role::Picker)->create(); // inventory.view

    $all = $this->actingAs($viewer, 'sanctum')->getJson("/api/v1/inventory/lots?product_id={$this->milk->id}")->assertOk()->assertJsonCount(2, 'data');
    expect($all->json('data.0.id'))->toBe($soon->id) // périme en premier
        ->and($all->json('data.0.available'))->toBe(5);

    $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/inventory/lots?blocked=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.recalled', true);
    $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/inventory/lots?expiring_in_days=3')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $soon->id);
});

it('restricts the lot list to staff who can view the stock', function () {
    asGuest();
    $this->getJson('/api/v1/inventory/lots')->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/inventory/lots')->assertForbidden();
    $this->actingAs(User::factory()->role(Role::Courier)->create(), 'sanctum')->getJson('/api/v1/inventory/lots')->assertForbidden();
});

it('does not touch the order itself: notifying and re-allocating stay explicit decisions', function () {
    $ctx = deliveredOrder([['product' => $this->milk, 'quantity' => 1]]);
    recall($this->lot)->assertOk();

    expect($ctx['order']->refresh()->status)->toBe(OrderStatus::Delivered);
});
