<?php

use App\Modules\Catalog\Enums\SaleUnit;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

beforeEach(function () {
    $this->customer = User::factory()->create();
    $this->rice = Product::factory()->create(['price_minor' => 5500, 'sale_unit' => SaleUnit::Piece]);
    $this->tomatoes = Product::factory()->byWeight()->create(['price_minor' => 900]);
    stockUp($this->rice, 100);
    stockUp($this->tomatoes, 50000);
});

it('requires authentication', function () {
    $this->postJson('/api/v1/orders', ['items' => [['product_id' => $this->rice->id, 'quantity' => 1]]])->assertUnauthorized();
});

it('places an order priced by the server', function () {
    $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [
            ['product_id' => $this->rice->id, 'quantity' => 2],
            ['product_id' => $this->tomatoes->id, 'quantity' => 1500],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'awaiting_payment')
        ->assertJsonPath('data.subtotal_minor', 11000 + 1350)
        ->assertJsonPath('data.total_minor', 12350)
        ->assertJsonCount(2, 'data.lines');

    expect($response->json('data.number'))->toStartWith('CMD-');
});

it('ignores prices sent by the client', function () {
    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $this->rice->id, 'quantity' => 1, 'unit_price_minor' => 1, 'price_minor' => 1]],
    ])->assertCreated()->assertJsonPath('data.total_minor', 5500);
});

it('keeps the price snapshot when the catalog price changes later', function () {
    $id = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $this->rice->id, 'quantity' => 1]],
    ])->json('data.id');

    $this->rice->update(['price_minor' => 9999]);

    $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/orders/{$id}")
        ->assertOk()->assertJsonPath('data.total_minor', 5500)->assertJsonPath('data.lines.0.unit_price_minor', 5500);
});

it('merges duplicate lines for the same product', function () {
    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [
            ['product_id' => $this->rice->id, 'quantity' => 1],
            ['product_id' => $this->rice->id, 'quantity' => 2],
        ],
    ])->assertCreated()->assertJsonCount(1, 'data.lines')->assertJsonPath('data.total_minor', 16500);
});

it('rejects unknown or inactive products', function () {
    $inactive = Product::factory()->create(['is_active' => false]);

    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $inactive->id, 'quantity' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items');

    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => 999999, 'quantity' => 1]],
    ])->assertUnprocessable();

    expect(Order::count())->toBe(0);
});

it('validates the payload', function (array $payload) {
    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', $payload)->assertUnprocessable();
})->with([
    'no items' => [['items' => []]],
    'zero quantity' => [['items' => [['product_id' => 1, 'quantity' => 0]]]],
    'fractional quantity' => [['items' => [['product_id' => 1, 'quantity' => 1.5]]]],
    'negative quantity' => [['items' => [['product_id' => 1, 'quantity' => -2]]]],
]);

it('is idempotent with the same Idempotency-Key', function () {
    $payload = ['items' => [['product_id' => $this->rice->id, 'quantity' => 1]]];
    $headers = ['Idempotency-Key' => 'abc-123'];

    $first = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', $payload, $headers)->assertCreated();
    $second = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', $payload, $headers)->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'))->and(Order::count())->toBe(1);
});

it('records the initial status in the history', function () {
    $id = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $this->rice->id, 'quantity' => 1]],
    ])->json('data.id');

    $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/orders/{$id}")
        ->assertJsonPath('data.history.0.to_status', 'awaiting_payment')
        ->assertJsonPath('data.history.0.from_status', null);
});
