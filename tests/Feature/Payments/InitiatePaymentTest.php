<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;

it('creates a pending payment whose amount comes from the order', function () {
    ['customer' => $customer, 'orderId' => $orderId, 'total' => $total] = awaitingPaymentOrder(2, 2500);

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/payments", ['method' => 'mobile_money', 'amount_minor' => 1])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount_minor', $total)
        ->assertJsonPath('data.currency', 'XOF');
});

it('requires authentication', function () {
    $this->postJson('/api/v1/orders/1/payments', ['method' => 'card'])->assertUnauthorized();
});

it('validates the payment method', function () {
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();

    startPayment($customer, $orderId, 'bitcoin')->assertUnprocessable()->assertJsonValidationErrors('method');
});

it('hides other customers\' orders behind a 404', function () {
    ['orderId' => $orderId] = awaitingPaymentOrder();

    startPayment(User::factory()->create(), $orderId)->assertNotFound();
    startPayment(User::factory()->create(), 999999)->assertNotFound();
    expect(Payment::count())->toBe(0);
});

it('is idempotent with the same Idempotency-Key', function () {
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();

    $first = startPayment($customer, $orderId, 'mobile_money', 'pay-1')->assertCreated();
    $second = startPayment($customer, $orderId, 'mobile_money', 'pay-1')->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'))->and(Payment::count())->toBe(1);
});

it('refuses to pay an order that is already paid or cancelled', function (string $status) {
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();
    $action = app(TransitionOrderAction::class);
    $order = Order::findOrFail($orderId);

    $action->execute($order, OrderStatus::from($status), null, 'test');

    startPayment($customer, $orderId)->assertUnprocessable()->assertJsonValidationErrors('order');
})->with(['paid', 'cancelled']);

it('lets the customer retry after a payment failure', function () {
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();
    app(TransitionOrderAction::class)->execute(Order::findOrFail($orderId), OrderStatus::PaymentFailed, null);

    startPayment($customer, $orderId)->assertCreated();
});

it('registers a cash on delivery payment that waits for operator validation', function () {
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();

    $paymentId = startPayment($customer, $orderId, 'cash_on_delivery')
        ->assertCreated()->assertJsonPath('data.status', 'pending_on_delivery')->json('data.id');

    expect(Order::find($orderId)->status)->toBe(OrderStatus::AwaitingPayment);

    $this->actingAs($customer, 'sanctum')->postJson("/api/v1/payments/{$paymentId}/validate-cash-on-delivery")->assertForbidden();

    $this->actingAs(User::factory()->role(Role::OrderOperator)->create(), 'sanctum')
        ->postJson("/api/v1/payments/{$paymentId}/validate-cash-on-delivery")->assertOk();

    expect(Order::find($orderId)->status)->toBe(OrderStatus::Paid)
        ->and(Payment::find($paymentId)->status->value)->toBe('pending_on_delivery');
});

it('refuses to validate a payment that is not cash on delivery', function () {
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();
    $paymentId = startPayment($customer, $orderId)->json('data.id');

    $this->actingAs(User::factory()->role(Role::Admin)->create(), 'sanctum')
        ->postJson("/api/v1/payments/{$paymentId}/validate-cash-on-delivery")->assertUnprocessable();
});
