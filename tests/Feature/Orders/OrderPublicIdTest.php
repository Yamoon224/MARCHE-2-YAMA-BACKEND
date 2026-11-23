<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

it('exposes an order by its UUID and never by its internal integer key', function () {
    $customer = User::factory()->create();
    $product = Product::factory()->create(['track_stock' => false]);

    $id = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
        ->assertCreated()->json('data.id');

    expect($id)->toBeString()->toMatch('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/');

    $internal = Order::where('uuid', $id)->firstOrFail()->id;

    $this->actingAs($customer, 'sanctum')->getJson("/api/v1/orders/{$id}")->assertOk()->assertJsonPath('data.id', $id);
    $this->actingAs($customer, 'sanctum')->getJson("/api/v1/orders/{$internal}")->assertNotFound();
    $this->actingAs($customer, 'sanctum')->getJson("/api/v1/orders/{$internal}/tracking")->assertNotFound();
});
