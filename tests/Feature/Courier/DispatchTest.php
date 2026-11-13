<?php

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

it('creates the delivery with a 6-digit code when a home delivery order becomes ready', function () {
    $ctx = readyDeliveryOrder();

    expect($ctx['delivery']->status->value)->toBe('pending')
        ->and($ctx['delivery']->confirmation_code)->toMatch('/^\d{6}$/')
        ->and(Delivery::count())->toBe(1);
});

it('creates no delivery for a store pickup order', function () {
    $product = pieceProduct();
    $customer = User::factory()->create();
    $orderId = test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->json('data.id');

    foreach ([OrderStatus::Paid, OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready] as $status) {
        app(TransitionOrderAction::class)->execute(Order::findOrFail($orderId), $status, null);
    }

    expect(Delivery::count())->toBe(0);
});

it('stores the confirmation code encrypted, never in clear', function () {
    $ctx = readyDeliveryOrder();

    $raw = DB::table('deliveries')->where('id', $ctx['delivery']->id)->value('confirmation_code');

    expect($raw)->not->toBe($ctx['delivery']->confirmation_code)->and($raw)->not->toContain($ctx['delivery']->confirmation_code);
});

it('lets dispatchers list and assign deliveries, and moves the order to assigned', function () {
    $ctx = readyDeliveryOrder();
    $courier = courierUser('Koffi');

    $this->actingAs(dispatcher(), 'sanctum')->getJson('/api/v1/delivery/deliveries')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending')->assertJsonPath('data.0.address.neighborhood', 'Cocody');

    assignCourier($ctx['delivery'], $courier)
        ->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.courier_id', $courier->id)->assertJsonPath('data.courier_name', 'Koffi');

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::Assigned);
});

it('never exposes the confirmation code to staff or couriers', function () {
    $ctx = readyDeliveryOrder();
    $courier = courierUser();
    assignCourier($ctx['delivery'], $courier)->assertOk();
    $code = $ctx['delivery']->confirmation_code;

    $this->actingAs(dispatcher(), 'sanctum')->getJson('/api/v1/delivery/deliveries')->assertOk();
    expect($this->actingAs(dispatcher(), 'sanctum')->getJson('/api/v1/delivery/deliveries')->getContent())->not->toContain($code);
    expect($this->actingAs($courier, 'sanctum')->getJson('/api/v1/delivery/my-tour')->getContent())->not->toContain($code);
    expect(assignCourier($ctx['delivery'], $courier)->getContent())->not->toContain($code);
});

it('lists the couriers for assignment, and only them', function () {
    courierUser('Zoé');
    courierUser('Adama');
    User::factory()->role(Role::Picker)->create(['name' => 'Préparateur']);
    $customer = User::factory()->create(['name' => 'Client']);

    $names = collect($this->actingAs(dispatcher(), 'sanctum')->getJson('/api/v1/delivery/couriers')->assertOk()->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Adama', 'Zoé']);
    $this->actingAs($customer, 'sanctum')->getJson('/api/v1/delivery/couriers')->assertForbidden();
    $this->actingAs(courierUser(), 'sanctum')->getJson('/api/v1/delivery/couriers')->assertForbidden();
});

it('refuses to assign an account that is not a courier', function () {
    $ctx = readyDeliveryOrder();

    assignCourier($ctx['delivery'], User::factory()->role(Role::Picker)->create())
        ->assertUnprocessable()->assertJsonPath('errors.delivery.0', "Ce compte n'est pas un livreur.");
    assignCourier($ctx['delivery'], $ctx['customer'])->assertUnprocessable();

    expect($ctx['delivery']->refresh()->status->value)->toBe('pending');
});

it('lets a dispatcher reassign before departure but not after', function () {
    $ctx = readyDeliveryOrder();
    $anna = courierUser('Anna');
    $ben = courierUser('Ben');

    assignCourier($ctx['delivery'], $anna)->assertOk();
    assignCourier($ctx['delivery'], $ben)->assertOk()->assertJsonPath('data.courier_id', $ben->id);

    courierCall($ben, $ctx['delivery'], 'start')->assertOk();
    assignCourier($ctx['delivery'], $anna)->assertUnprocessable();
    expect($ctx['delivery']->refresh()->courier_id)->toBe($ben->id);
});

it('restricts dispatching to authorised staff', function () {
    $ctx = readyDeliveryOrder();
    $courier = courierUser();

    asGuest();
    $this->getJson('/api/v1/delivery/deliveries')->assertUnauthorized();
    foreach ([$ctx['customer'], $courier, User::factory()->role(Role::Picker)->create(), User::factory()->role(Role::Support)->create()] as $user) {
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/delivery/deliveries')->assertForbidden();
        assignCourier($ctx['delivery'], $courier, $user)->assertForbidden();
    }

    assignCourier($ctx['delivery'], $courier, User::factory()->role(Role::Admin)->create())->assertOk();
});

it('cannot assign a delivery whose order was cancelled meanwhile', function () {
    $ctx = readyDeliveryOrder();
    $ctx['order']->update(['status' => OrderStatus::Cancelled]);

    assignCourier($ctx['delivery'], courierUser())->assertUnprocessable();
});

it('blocks the delivery stages of a home delivery order in the general orders API', function (string $to) {
    $ctx = readyDeliveryOrder();
    $admin = User::factory()->role(Role::Admin)->create();

    $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$ctx['order']->id}/transitions", ['to' => $to, 'reason' => 'test'])
        ->assertUnprocessable()->assertJsonValidationErrors('to');

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::Ready);
})->with(['assigned', 'out_for_delivery', 'delivered', 'delivery_failed', 'returned']);

it('still lets store pickup orders be handed over through the general API', function () {
    $product = pieceProduct();
    $customer = User::factory()->create();
    $orderId = test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->json('data.id');
    foreach ([OrderStatus::Paid, OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready] as $status) {
        app(TransitionOrderAction::class)->execute(Order::findOrFail($orderId), $status, null);
    }

    $this->actingAs(User::factory()->role(Role::Admin)->create(), 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/transitions", ['to' => 'delivered'])->assertOk()->assertJsonPath('data.status', 'delivered');
});
