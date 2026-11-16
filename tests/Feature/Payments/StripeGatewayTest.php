<?php

use App\Modules\Payments\DTO\WebhookEvent;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\GatewayFailure;
use App\Modules\Payments\Exceptions\IgnoredWebhook;
use App\Modules\Payments\Exceptions\InvalidWebhook;
use App\Modules\Payments\Gateways\GatewayRegistry;
use App\Modules\Payments\Gateways\StripeGateway;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const STRIPE_WHSEC = 'whsec_test_secret';

function stripe(?string $apiKey = 'sk_test_123'): StripeGateway
{
    return new StripeGateway($apiKey, STRIPE_WHSEC, 'https://shop.example.test');
}

function stripeSignature(string $body, ?int $time = null, string $secret = STRIPE_WHSEC): string
{
    $time ??= time();

    return "t={$time},v1=".hash_hmac('sha256', $time.'.'.$body, $secret);
}

function stripeEvent(string $type, array $object = [], string $id = 'evt_1'): string
{
    return json_encode(['id' => $id, 'type' => $type, 'data' => ['object' => ['id' => 'cs_test_1', 'amount_total' => 5000, 'currency' => 'eur', 'payment_status' => 'paid', ...$object]]]);
}

beforeEach(function () {
    config(['payments.gateways.stripe' => [
        'driver' => StripeGateway::class, 'api_key' => 'sk_test_123', 'secret' => STRIPE_WHSEC,
        'return_url' => 'https://shop.example.test', 'api_base' => 'https://api.stripe.com',
    ], 'payments.default' => 'stripe']);
});

it('creates a hosted Checkout session with a server-fixed amount and idempotency', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);

    $charge = stripe()->charge('PAY-ABC', 5000, 'EUR', PaymentMethod::Card, ['order_id' => 42]);

    expect($charge->providerReference)->toBe('cs_test_1')->and($charge->actionUrl)->toBe('https://checkout.stripe.com/c/pay/cs_test_1');

    Http::assertSent(function (Request $request) {
        parse_str($request->body(), $form);

        return $request->hasHeader('Authorization', 'Bearer sk_test_123')
            && $request->hasHeader('Idempotency-Key', 'PAY-ABC')
            && $form['mode'] === 'payment'
            && $form['line_items'][0]['price_data']['unit_amount'] === '5000'
            && $form['line_items'][0]['price_data']['currency'] === 'eur'
            && $form['payment_method_types'][0] === 'card'
            && $form['client_reference_id'] === 'PAY-ABC'
            && $form['success_url'] === 'https://shop.example.test/commandes/42?paiement=card'
            && $form['cancel_url'] === 'https://shop.example.test/commandes/42?paiement=annule';
    });
});

it('refuses cleanly while the API key is missing, without calling Stripe', function () {
    Http::fake();

    expect(fn () => stripe(null)->charge('PAY-ABC', 5000, 'EUR', PaymentMethod::Card))->toThrow(GatewayFailure::class, 'pas encore configuré');
    expect(fn () => stripe('')->charge('PAY-ABC', 5000, 'EUR', PaymentMethod::Card))->toThrow(GatewayFailure::class);

    Http::assertNothingSent();
});

it('surfaces Stripe errors without leaking the key', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid currency']], 400)]);

    expect(fn () => stripe()->charge('PAY-ABC', 5000, 'EUR', PaymentMethod::Card))
        ->toThrow(GatewayFailure::class, 'Invalid currency');
});

it('refunds through the PaymentIntent of the Checkout session, idempotently', function () {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'payment_intent' => 'pi_9']),
        'api.stripe.com/v1/refunds' => Http::response(['id' => 're_1']),
    ]);

    expect(stripe()->refund('cs_test_1', 1200, 'EUR', 'RF-1')->providerReference)->toBe('re_1');

    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/v1/refunds')) {
            return true;
        }
        parse_str($request->body(), $form);

        return $form['payment_intent'] === 'pi_9' && $form['amount'] === '1200' && $request->hasHeader('Idempotency-Key', 'RF-1');
    });
});

it('turns a Stripe refund failure into a retryable GatewayFailure', function () {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['payment_intent' => 'pi_9']),
        'api.stripe.com/v1/refunds' => Http::response(['error' => ['message' => 'Charge already refunded']], 400),
    ]);

    expect(fn () => stripe()->refund('cs_test_1', 100, 'EUR', 'RF-2'))->toThrow(GatewayFailure::class, 'Remboursement refusé');
});

it('verifies the Stripe-Signature header', function () {
    $body = stripeEvent('checkout.session.completed');

    expect(stripe()->verifySignature($body, stripeSignature($body)))->toBeTrue();
    expect(stripe()->verifySignature($body.' ', stripeSignature($body)))->toBeFalse();
    expect(stripe()->verifySignature($body, stripeSignature($body, secret: 'whsec_autre')))->toBeFalse();
    expect(stripe()->verifySignature($body, stripeSignature($body, time() - 3600)))->toBeFalse();
    expect(stripe()->verifySignature($body, 't=abc,v1=zz'))->toBeFalse();
    expect(stripe()->verifySignature($body, null))->toBeFalse();
    expect((new StripeGateway('sk', null, 'https://x'))->verifySignature($body, stripeSignature($body)))->toBeFalse();
});

it('accepts a signature that carries several v1 values (secret rotation)', function () {
    $body = stripeEvent('checkout.session.completed');
    $time = time();
    $good = hash_hmac('sha256', $time.'.'.$body, STRIPE_WHSEC);

    expect(stripe()->verifySignature($body, "t={$time},v1=deadbeef,v1={$good}"))->toBeTrue();
});

it('maps Checkout events to webhook events', function () {
    $paid = stripe()->parseWebhook(stripeEvent('checkout.session.completed'));
    expect($paid)->toBeInstanceOf(WebhookEvent::class)->and($paid->succeeded)->toBeTrue()
        ->and($paid->providerReference)->toBe('cs_test_1')->and($paid->amountMinor)->toBe(5000)->and($paid->currency)->toBe('EUR');

    expect(stripe()->parseWebhook(stripeEvent('checkout.session.async_payment_failed'))->succeeded)->toBeFalse();
    expect(stripe()->parseWebhook(stripeEvent('checkout.session.expired', ['payment_status' => 'unpaid']))->succeeded)->toBeFalse();
});

it('ignores unhandled event types and unpaid completions, rejects malformed payloads', function () {
    expect(fn () => stripe()->parseWebhook(stripeEvent('charge.succeeded')))->toThrow(IgnoredWebhook::class);
    expect(fn () => stripe()->parseWebhook(stripeEvent('checkout.session.completed', ['payment_status' => 'unpaid'])))->toThrow(IgnoredWebhook::class);
    expect(fn () => stripe()->parseWebhook('{"foo":1}'))->toThrow(InvalidWebhook::class);
    expect(fn () => stripe()->parseWebhook('pas du json'))->toThrow(InvalidWebhook::class);
});

it('is built from the configuration by the registry, in production too', function () {
    expect(app(GatewayRegistry::class)->get('stripe'))->toBeInstanceOf(StripeGateway::class);

    $this->app['env'] = 'production';
    expect(app(GatewayRegistry::class)->default())->toBeInstanceOf(StripeGateway::class);
});

it('starts a card payment through Stripe and exposes the hosted page URL', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_live_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_live_1'])]);
    ['customer' => $customer, 'orderId' => $orderId, 'total' => $total] = awaitingPaymentOrder();

    startPayment($customer, $orderId, 'card')->assertCreated()
        ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.amount_minor', $total)
        ->assertJsonPath('data.action_url', 'https://checkout.stripe.com/c/pay/cs_live_1');

    expect(Payment::where('order_id', $orderId)->value('provider'))->toBe('stripe');
});

it('answers 503 and leaves the order payable when Stripe is not configured yet', function () {
    config(['payments.gateways.stripe.api_key' => null]);
    Http::fake();
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();

    startPayment($customer, $orderId, 'card')->assertStatus(503)->assertJsonPath('message', fn ($m) => str_contains($m, 'pas encore configuré'));

    expect(Payment::where('order_id', $orderId)->count())->toBe(0);
    Http::assertNothingSent();
});

it('marks the order paid when Stripe confirms the payment through a signed webhook, once', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_live_2', 'url' => 'https://checkout.stripe.com/c/pay/cs_live_2'])]);
    ['customer' => $customer, 'orderId' => $orderId, 'total' => $total] = awaitingPaymentOrder();
    $paymentId = startPayment($customer, $orderId, 'card')->assertCreated()->json('data.id');

    $body = stripeEvent('checkout.session.completed', ['id' => 'cs_live_2', 'amount_total' => $total], 'evt_paid');
    $send = fn (string $signature) => $this->call('POST', '/api/v1/webhooks/payments/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature, 'HTTP_ACCEPT' => 'application/json',
    ], $body);

    $send('t='.time().',v1=forgé')->assertUnauthorized();
    expect(Payment::find($paymentId)->status)->toBe(PaymentStatus::Pending);

    $send(stripeSignature($body))->assertOk()->assertJsonPath('outcome', 'succeeded');
    expect(Payment::find($paymentId)->status)->toBe(PaymentStatus::Succeeded);

    $send(stripeSignature($body))->assertOk()->assertJsonPath('outcome', 'duplicate');
});

it('flags a Stripe payment whose amount differs from the order', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_live_3', 'url' => 'https://checkout.stripe.com/x'])]);
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();
    $paymentId = startPayment($customer, $orderId, 'card')->json('data.id');

    $body = stripeEvent('checkout.session.completed', ['id' => 'cs_live_3', 'amount_total' => 1], 'evt_bad');
    $this->call('POST', '/api/v1/webhooks/payments/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => stripeSignature($body), 'HTTP_ACCEPT' => 'application/json',
    ], $body)->assertOk()->assertJsonPath('outcome', 'flagged');

    expect(Payment::find($paymentId)->status)->toBe(PaymentStatus::Flagged);
});

it('acknowledges unhandled Stripe events with 200 so they are not retried', function () {
    $body = stripeEvent('charge.succeeded');

    $this->call('POST', '/api/v1/webhooks/payments/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => stripeSignature($body), 'HTTP_ACCEPT' => 'application/json',
    ], $body)->assertOk()->assertJsonPath('outcome', 'ignored');
});
