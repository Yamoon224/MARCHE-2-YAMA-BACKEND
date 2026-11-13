<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

it('shows customers only their own orders', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Order::factory()->forUser($alice->id)->count(2)->create();
    Order::factory()->forUser($bob->id)->create();

    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/orders')->assertOk()->assertJsonCount(2, 'data');
});

it('shows every order to staff with order access', function () {
    Order::factory()->forUser(User::factory()->create()->id)->count(3)->create();

    $this->actingAs(User::factory()->role(Role::OrderOperator)->create(), 'sanctum')
        ->getJson('/api/v1/orders')->assertOk()->assertJsonCount(3, 'data');
});

it('filters by status', function () {
    $operator = User::factory()->role(Role::Admin)->create();
    Order::factory()->create();
    Order::factory()->status(OrderStatus::Paid)->create();

    $this->actingAs($operator, 'sanctum')->getJson('/api/v1/orders?status=paid')->assertOk()->assertJsonCount(1, 'data');
});

it('forbids reading someone else\'s order', function () {
    $order = Order::factory()->forUser(User::factory()->create()->id)->create();

    $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
    $this->actingAs(User::factory()->role(Role::Support)->create(), 'sanctum')->getJson("/api/v1/orders/{$order->id}")->assertOk();
});

it('requires authentication to list orders', function () {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});
