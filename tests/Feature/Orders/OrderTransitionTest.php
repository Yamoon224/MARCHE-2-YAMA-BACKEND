<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Event;

function staff(Role $role): User
{
    return User::factory()->role($role)->create();
}

function orderIn(OrderStatus $status, ?User $owner = null): Order
{
    return Order::factory()->status($status)->forUser(($owner ?? User::factory()->create())->id)->create();
}

function transition(User $user, Order $order, string $to, ?string $reason = null)
{
    return test()->actingAs($user, 'sanctum')->postJson("/api/v1/orders/{$order->id}/transitions", array_filter(['to' => $to, 'reason' => $reason]));
}

it('lets an order operator confirm a paid order and records who and when', function () {
    $operator = staff(Role::OrderOperator);
    $order = orderIn(OrderStatus::Paid);

    transition($operator, $order, 'confirmed')
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.history.0.to_status', 'confirmed')
        ->assertJsonPath('data.history.0.user_id', $operator->id);

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

it('refuses a forbidden transition with 422 and leaves the status untouched', function () {
    $order = orderIn(OrderStatus::AwaitingPayment);

    transition(staff(Role::Admin), $order, 'delivered')->assertUnprocessable()->assertJsonValidationErrors('to');

    expect($order->refresh()->status)->toBe(OrderStatus::AwaitingPayment)->and($order->history()->count())->toBe(0);
});

it('rejects an unknown status', function () {
    transition(staff(Role::Admin), orderIn(OrderStatus::Paid), 'teleported')->assertUnprocessable();
});

it('requires a reason to cancel', function () {
    $operator = staff(Role::OrderOperator);
    $order = orderIn(OrderStatus::Confirmed);

    transition($operator, $order, 'cancelled')->assertUnprocessable();
    transition($operator, $order, 'cancelled', 'Client injoignable')
        ->assertOk()->assertJsonPath('data.history.0.reason', 'Client injoignable');
});

it('lets the owner cancel before handover but not another customer', function () {
    $owner = User::factory()->create();
    $order = orderIn(OrderStatus::Confirmed, $owner);

    transition(User::factory()->create(), $order, 'cancelled', 'test')->assertForbidden();
    transition($owner, $order, 'cancelled', 'Changement d\'avis')->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('does not let the owner cancel once the courier has the order', function () {
    $owner = User::factory()->create();

    transition($owner, orderIn(OrderStatus::OutForDelivery, $owner), 'cancelled', 'trop tard')->assertUnprocessable();
});

it('does not let a customer confirm or pay their own order', function (string $to, OrderStatus $from) {
    $owner = User::factory()->create();

    transition($owner, orderIn($from, $owner), $to)->assertForbidden();
})->with([
    'confirm' => ['confirmed', OrderStatus::Paid],
    'mark paid' => ['paid', OrderStatus::AwaitingPayment],
    'mark delivered' => ['delivered', OrderStatus::OutForDelivery],
]);

it('limits pickers to preparation and couriers to delivery', function () {
    $picker = staff(Role::Picker);
    $courier = staff(Role::Courier);

    transition($picker, orderIn(OrderStatus::Confirmed), 'preparing')->assertOk();
    // « prête » s'obtient en terminant la liste de préparation, pas par l'API des commandes (voir module Fulfillment)
    transition($picker, orderIn(OrderStatus::Preparing), 'ready')->assertUnprocessable();
    transition($picker, orderIn(OrderStatus::Paid), 'confirmed')->assertForbidden();
    transition($picker, orderIn(OrderStatus::OutForDelivery), 'delivered')->assertForbidden();

    transition($courier, orderIn(OrderStatus::Assigned), 'out_for_delivery')->assertOk();
    transition($courier, orderIn(OrderStatus::OutForDelivery), 'delivered')->assertOk();
    transition($courier, orderIn(OrderStatus::OutForDelivery), 'delivery_failed', 'Client absent')->assertOk();
    transition($courier, orderIn(OrderStatus::Confirmed), 'preparing')->assertForbidden();
});

it('lets the owner open a claim after delivery, with a reason', function () {
    $owner = User::factory()->create();
    $order = orderIn(OrderStatus::Delivered, $owner);

    transition($owner, $order, 'claim')->assertUnprocessable();
    transition($owner, $order, 'claim', 'Produit abîmé')->assertOk()->assertJsonPath('data.status', 'claim');
});

it('requires authentication', function () {
    $this->postJson('/api/v1/orders/1/transitions', ['to' => 'confirmed'])->assertUnauthorized();
});

it('dispatches a domain event on each transition', function () {
    Event::fake([OrderStatusChanged::class]);
    $order = orderIn(OrderStatus::Paid);

    transition(staff(Role::Admin), $order, 'confirmed')->assertOk();

    Event::assertDispatched(OrderStatusChanged::class, fn ($e) => $e->orderId === $order->id
        && $e->from === OrderStatus::Paid && $e->to === OrderStatus::Confirmed);
});

it('does not dispatch an event when the transition is refused', function () {
    Event::fake([OrderStatusChanged::class]);

    transition(staff(Role::Admin), orderIn(OrderStatus::Paid), 'delivered')->assertUnprocessable();

    Event::assertNotDispatched(OrderStatusChanged::class);
});

it('walks a full delivery lifecycle through the API', function () {
    $owner = User::factory()->create();
    $order = orderIn(OrderStatus::AwaitingPayment, $owner);
    $admin = staff(Role::Admin);

    foreach (['paid', 'confirmed', 'preparing', 'ready', 'assigned', 'out_for_delivery', 'delivered', 'completed'] as $to) {
        if ($to === 'ready') {
            // la préparation se termine côté Fulfillment ; on simule ici sa clôture
            app(TransitionOrderAction::class)->execute($order->refresh(), OrderStatus::Ready, null);

            continue;
        }

        transition($admin, $order, $to)->assertOk()->assertJsonPath('data.status', $to);
    }

    expect($order->history()->count())->toBe(8);
    transition($admin, $order, 'cancelled', 'trop tard')->assertUnprocessable();
});
