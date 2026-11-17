<?php

use App\Modules\Delivery\Models\DeliveryLocation;
use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

it('shows a courier only his own deliveries, with what he needs on the doorstep', function () {
    $mine = readyDeliveryOrder();
    $other = readyDeliveryOrder();
    $me = courierUser('Moi');
    assignCourier($mine['delivery'], $me)->assertOk();
    assignCourier($other['delivery'], courierUser('Autre'))->assertOk();

    $response = $this->actingAs($me, 'sanctum')->getJson('/api/v1/delivery/my-tour')->assertOk()->assertJsonCount(1, 'data');

    expect($response->json('data.0.order_id'))->toBe($mine['order']->id)
        ->and($response->json('data.0.phone'))->toBe('+2250700112233')
        ->and($response->json('data.0.address.landmark'))->toBe('Portail bleu')
        ->and($response->json('data.0.cash_due_minor'))->toBe(0); // déjà payée en ligne
});

it('orders the tour by delivery slot', function () {
    $late = readyDeliveryOrder();
    $early = readyDeliveryOrder();
    DeliverySlot::whereKey($late['slot']->id)->update(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2)]);
    DeliverySlot::whereKey($early['slot']->id)->update(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2)]);
    $me = courierUser();
    assignCourier($late['delivery'], $me)->assertOk();
    assignCourier($early['delivery'], $me)->assertOk();

    $this->actingAs($me, 'sanctum')->getJson('/api/v1/delivery/my-tour')
        ->assertOk()->assertJsonPath('data.0.order_id', $early['order']->id)->assertJsonPath('data.1.order_id', $late['order']->id);
});

it('restricts the tour to couriers', function () {
    asGuest();
    $this->getJson('/api/v1/delivery/my-tour')->assertUnauthorized();
    foreach ([User::factory()->create(), User::factory()->role(Role::Picker)->create(), dispatcher()] as $user) {
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/delivery/my-tour')->assertForbidden();
    }
});

it('hides other couriers\' deliveries behind a 404 for every action', function (string $action, array $body) {
    $ctx = readyDeliveryOrder();
    assignCourier($ctx['delivery'], courierUser('Propriétaire'))->assertOk();

    courierCall(courierUser('Intrus'), $ctx['delivery'], $action, $body)->assertNotFound();
})->with([
    'start' => ['start', []],
    'location' => ['location', ['latitude' => 5.3, 'longitude' => -4.0]],
    'confirm' => ['confirm', ['code' => '123456', 'cash_collected_minor' => 0]],
    'fail' => ['fail', ['reason' => 'Absent']],
]);

it('starts the delivery: the order goes out for delivery', function () {
    $ctx = readyDeliveryOrder();
    $courier = courierUser();
    assignCourier($ctx['delivery'], $courier)->assertOk();

    courierCall($courier, $ctx['delivery'], 'start')->assertOk()->assertJsonPath('data.status', 'out_for_delivery');

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::OutForDelivery);
});

it('cannot start a delivery that is not assigned yet or already left', function () {
    $ctx = readyDeliveryOrder();
    $courier = courierUser();

    courierCall($courier, $ctx['delivery'], 'start')->assertNotFound(); // pas encore à lui

    assignCourier($ctx['delivery'], $courier)->assertOk();
    courierCall($courier, $ctx['delivery'], 'start')->assertOk();
    courierCall($courier, $ctx['delivery'], 'start')->assertUnprocessable();
});

// ───────────────────────── position ─────────────────────────

it('records only the last position, and only while out for delivery', function () {
    $ctx = readyDeliveryOrder();
    $courier = courierUser();
    assignCourier($ctx['delivery'], $courier)->assertOk();

    courierCall($courier, $ctx['delivery'], 'location', ['latitude' => 5.3, 'longitude' => -4.0])->assertUnprocessable(); // pas parti

    courierCall($courier, $ctx['delivery'], 'start')->assertOk();
    courierCall($courier, $ctx['delivery'], 'location', ['latitude' => 5.30, 'longitude' => -4.00])->assertNoContent();
    courierCall($courier, $ctx['delivery'], 'location', ['latitude' => 5.31, 'longitude' => -4.01])->assertNoContent();

    expect(DeliveryLocation::count())->toBe(1)
        ->and(DeliveryLocation::first()->latitude)->toBe(5.31);
});

it('validates coordinates', function (array $body) {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);

    courierCall($courier, $ctx['delivery'], 'location', $body)->assertUnprocessable();
})->with([
    'latitude too high' => [['latitude' => 91, 'longitude' => 0]],
    'longitude too low' => [['latitude' => 0, 'longitude' => -181]],
    'text' => [['latitude' => 'abc', 'longitude' => 0]],
    'missing' => [['latitude' => 5.3]],
]);

it('shows the customer the live position, the courier and the code - and nobody else', function () {
    $ctx = readyDeliveryOrder();
    $courier = courierUser('Koffi');
    $url = "/api/v1/orders/{$ctx['order']->id}/tracking";
    $code = $ctx['delivery']->confirmation_code;

    $this->actingAs($ctx['customer'], 'sanctum')->getJson($url)
        ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.confirmation_code', $code)->assertJsonPath('data.location', null);

    assignCourier($ctx['delivery'], $courier)->assertOk();
    courierCall($courier, $ctx['delivery'], 'start')->assertOk();
    courierCall($courier, $ctx['delivery'], 'location', ['latitude' => 5.31, 'longitude' => -4.01])->assertNoContent();

    $this->actingAs($ctx['customer'], 'sanctum')->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.status', 'out_for_delivery')
        ->assertJsonPath('data.courier_name', 'Koffi')
        ->assertJsonPath('data.confirmation_code', $code)
        ->assertJsonPath('data.location.latitude', 5.31);

    // Le personnel voit la position mais jamais le code ; un autre client ne voit rien.
    $staff = $this->actingAs(User::factory()->role(Role::Support)->create(), 'sanctum')->getJson($url)->assertOk();
    expect($staff->json('data.confirmation_code'))->toBeNull()->and($staff->json('data.location.latitude'))->toBe(5.31);
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertNotFound();
    $this->actingAs(courierUser('Autre'), 'sanctum')->getJson($url)->assertNotFound();
    asGuest();
    $this->getJson($url)->assertUnauthorized();
});

it('gives no tracking for an order without home delivery', function () {
    $product = pieceProduct();
    $customer = User::factory()->create();
    $orderId = test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->json('data.id');

    $this->actingAs($customer, 'sanctum')->getJson("/api/v1/orders/{$orderId}/tracking")->assertNotFound();
});
