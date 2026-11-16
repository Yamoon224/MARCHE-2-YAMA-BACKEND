<?php

use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Payments\Gateways\FakeGateway;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->ctx = awaitingPaymentOrder(2, 2500);
    $payment = startPayment($this->ctx['customer'], $this->ctx['orderId'])->assertCreated();
    $this->paymentId = $payment->json('data.id');
    $this->providerRef = Payment::findOrFail($this->paymentId)->provider_reference;
    $this->total = $this->ctx['total'];
});

it('marks the payment and the order paid and holds the stock', function () {
    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-1'))
        ->assertOk()->assertJsonPath('outcome', 'succeeded');

    expect(Payment::find($this->paymentId)->status->value)->toBe('succeeded')
        ->and(Order::find($this->ctx['orderId'])->status)->toBe(OrderStatus::Paid)
        ->and($this->ctx['lot']->refresh()->quantity_reserved)->toBe(2);

    // La réservation n'expire plus une fois la commande payée.
    $this->travel(2)->hours();
    $this->artisan('inventory:release-expired')->assertSuccessful();
    expect($this->ctx['lot']->refresh()->quantity_reserved)->toBe(2);
});

it('rejects a webhook with a bad or missing signature', function () {
    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-2'), 'forged')->assertUnauthorized();

    expect(Payment::find($this->paymentId)->status->value)->toBe('pending')
        ->and(Order::find($this->ctx['orderId'])->status)->toBe(OrderStatus::AwaitingPayment)
        ->and(PaymentWebhookEvent::count())->toBe(0);
});

it('rejects a payload signed for another body', function () {
    $good = json_encode(webhookFor($this->providerRef, 'failed', $this->total, 'evt-3'));
    $signature = (new FakeGateway(config('payments.gateways.fake.secret')))->sign($good);

    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-3'), $signature)->assertUnauthorized();
});

it('rejects malformed events and unknown providers', function () {
    sendWebhook(['event_id' => 'x', 'status' => 'succeeded'])->assertUnprocessable();
    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-4'), null, 'nope')->assertNotFound();
});

it('has a single effect when the same event is replayed', function () {
    Event::fake([PaymentSucceeded::class]);
    $payload = webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-5');

    sendWebhook($payload)->assertOk()->assertJsonPath('outcome', 'succeeded');
    sendWebhook($payload)->assertOk()->assertJsonPath('outcome', 'duplicate');
    sendWebhook($payload)->assertOk()->assertJsonPath('outcome', 'duplicate');

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
    expect(PaymentWebhookEvent::count())->toBe(1)
        ->and(Order::find($this->ctx['orderId'])->history()->count())->toBe(2); // création + payée
});

it('does not reopen a succeeded payment when a late failure arrives (out of order)', function () {
    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-6'))->assertOk();
    sendWebhook(webhookFor($this->providerRef, 'failed', $this->total, 'evt-7'))->assertOk()->assertJsonPath('outcome', 'ignored');

    expect(Payment::find($this->paymentId)->status->value)->toBe('succeeded')
        ->and(Order::find($this->ctx['orderId'])->status)->toBe(OrderStatus::Paid);
});

it('moves the order to payment failed on a failure, and recovers on a later success', function () {
    sendWebhook(webhookFor($this->providerRef, 'failed', $this->total, 'evt-8'))->assertOk()->assertJsonPath('outcome', 'failed');

    expect(Payment::find($this->paymentId)->status->value)->toBe('failed')
        ->and(Order::find($this->ctx['orderId'])->status)->toBe(OrderStatus::PaymentFailed);

    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-9'))->assertOk()->assertJsonPath('outcome', 'succeeded');

    expect(Payment::find($this->paymentId)->status->value)->toBe('succeeded')
        ->and(Order::find($this->ctx['orderId'])->status)->toBe(OrderStatus::Paid);
});

it('flags a wrong amount instead of paying the order', function () {
    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total - 1, 'evt-10'))
        ->assertOk()->assertJsonPath('outcome', 'flagged');

    $payment = Payment::find($this->paymentId);
    expect($payment->status->value)->toBe('flagged')
        ->and($payment->note)->toContain('inattendus')
        ->and(Order::find($this->ctx['orderId'])->status)->toBe(OrderStatus::AwaitingPayment);
});

it('flags a wrong currency', function () {
    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-11', 'XOF'))
        ->assertOk()->assertJsonPath('outcome', 'flagged');

    expect(Order::find($this->ctx['orderId'])->status)->toBe(OrderStatus::AwaitingPayment);
});

it('records money received for an order that was cancelled meanwhile and asks for a refund', function () {
    app(TransitionOrderAction::class)->execute(Order::findOrFail($this->ctx['orderId']), OrderStatus::Cancelled, null, 'Expirée');

    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-12'))->assertOk();

    $payment = Payment::find($this->paymentId);
    expect($payment->status->value)->toBe('succeeded')
        ->and($payment->needs_refund)->toBeTrue()
        ->and(Order::find($this->ctx['orderId'])->status)->toBe(OrderStatus::Cancelled)
        ->and($this->ctx['lot']->refresh()->quantity_reserved)->toBe(0);
});

it('ignores events for unknown payments but still records them', function () {
    sendWebhook(webhookFor('fake_unknown', 'succeeded', 100, 'evt-13'))->assertOk()->assertJsonPath('outcome', 'ignored');

    expect(PaymentWebhookEvent::where('event_id', 'evt-13')->value('outcome'))->toBe('ignored');
});

it('dispatches PaymentSucceeded with the verified amount', function () {
    Event::fake([PaymentSucceeded::class]);

    sendWebhook(webhookFor($this->providerRef, 'succeeded', $this->total, 'evt-14'))->assertOk();

    Event::assertDispatched(PaymentSucceeded::class, fn ($e) => $e->paymentId === $this->paymentId
        && $e->orderId === $this->ctx['orderId'] && $e->amountMinor === $this->total && $e->currency === 'EUR');
});
