<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Payments\Gateways\FakeGateway;
use App\Modules\Payments\Models\Payment;
use Illuminate\Testing\TestResponse;

/** Crée un client, un produit en stock et une commande en attente de paiement (via l'API). */
function awaitingPaymentOrder(int $quantity = 2, int $price = 2500, int $vatBp = 0): array
{
    $customer = User::factory()->create();
    $product = Product::factory()->create(['price_minor' => $price, 'vat_rate_bp' => $vatBp]);
    $lot = stockUp($product, 20);

    $orderId = test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
    ])->assertCreated()->json('data.id');

    return ['customer' => $customer, 'orderId' => $orderId, 'lot' => $lot, 'total' => $quantity * $price];
}

/** Paie la commande par webhook signé (mobile money) et retourne l'identifiant du paiement. */
function payOrder(array $ctx, string $eventId = 'evt-pay'): int
{
    $paymentId = startPayment($ctx['customer'], $ctx['orderId'])->assertCreated()->json('data.id');
    $providerRef = Payment::findOrFail($paymentId)->provider_reference;

    sendWebhook(webhookFor($providerRef, 'succeeded', $ctx['total'], $eventId))->assertOk();

    return $paymentId;
}

/** Démarre un paiement mobile money et retourne [paiement JSON, référence agrégateur]. */
function startPayment(User $customer, int $orderId, string $method = 'mobile_money', ?string $key = null): TestResponse
{
    return test()->actingAs($customer, 'sanctum')->postJson(
        "/api/v1/orders/{$orderId}/payments",
        ['method' => $method],
        $key ? ['Idempotency-Key' => $key] : [],
    );
}

/** Envoie un webhook signé comme le ferait l'agrégateur. */
function sendWebhook(array $payload, ?string $signature = null, string $provider = 'fake'): TestResponse
{
    $body = json_encode($payload);
    $signature ??= (new FakeGateway((string) config('payments.gateways.fake.secret')))->sign($body);

    return test()->call(
        'POST',
        "/api/v1/webhooks/payments/{$provider}",
        [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature, 'HTTP_ACCEPT' => 'application/json'],
        $body,
    );
}

function webhookFor(string $providerReference, string $status, int $amount, string $eventId, string $currency = 'EUR'): array
{
    return [
        'event_id' => $eventId, 'provider_reference' => $providerReference, 'status' => $status,
        'amount_minor' => $amount, 'currency' => $currency,
    ];
}
