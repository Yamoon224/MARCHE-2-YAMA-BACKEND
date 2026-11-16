<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\DTO\GatewayCharge;
use App\Modules\Payments\DTO\GatewayRefund;
use App\Modules\Payments\DTO\WebhookEvent;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Exceptions\GatewayFailure;
use App\Modules\Payments\Exceptions\IgnoredWebhook;
use App\Modules\Payments\Exceptions\InvalidWebhook;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout (page de paiement hébergée par Stripe : les numéros de carte ne passent jamais par nos serveurs).
 * Appels HTTP directs (pas de SDK) ; webhooks vérifiés par la signature `Stripe-Signature` (HMAC-SHA256, horodatage borné).
 *
 * Tant que STRIPE_SECRET_KEY n'est pas renseignée, `charge()` refuse proprement : rien d'autre ne change.
 */
final class StripeGateway implements PaymentGateway
{
    /** Écart maximal toléré entre l'horodatage signé et l'heure du serveur (anti-rejeu). */
    private const TOLERANCE_SECONDS = 300;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?string $webhookSecret,
        private readonly string $returnUrl,
        private readonly string $apiBase = 'https://api.stripe.com',
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): self
    {
        return new self(
            $config['api_key'] ?? null,
            $config['secret'] ?? null,
            rtrim((string) ($config['return_url'] ?? ''), '/'),
            (string) ($config['api_base'] ?? 'https://api.stripe.com'),
        );
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function charge(string $reference, int $amountMinor, string $currency, PaymentMethod $method, array $context = []): GatewayCharge
    {
        $orderId = $context['order_id'] ?? null;
        $back = $this->returnUrl.($orderId !== null ? "/commandes/{$orderId}" : '/commandes');

        $session = $this->post('/v1/checkout/sessions', [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $amountMinor,
                    'product_data' => ['name' => 'Commande Marché de Yama'],
                ],
            ]],
            'client_reference_id' => $reference,
            'metadata' => ['reference' => $reference],
            'success_url' => $back.'?paiement=card',
            'cancel_url' => $back.'?paiement=annule',
        ], $reference);

        if (! is_string($session['id'] ?? null) || ! is_string($session['url'] ?? null)) {
            throw GatewayFailure::chargeFailed('réponse inattendue');
        }

        return new GatewayCharge($session['id'], $session['url']);
    }

    public function refund(string $providerReference, int $amountMinor, string $currency, string $reference): GatewayRefund
    {
        // Le paiement vit sous un PaymentIntent : on le retrouve depuis la session Checkout enregistrée.
        $session = $this->get("/v1/checkout/sessions/{$providerReference}");
        $intent = $session['payment_intent'] ?? null;

        if (! is_string($intent)) {
            throw GatewayFailure::refundFailed('paiement introuvable chez Stripe');
        }

        try {
            $refund = $this->post('/v1/refunds', [
                'payment_intent' => $intent,
                'amount' => $amountMinor,
                'metadata' => ['reference' => $reference],
            ], $reference);
        } catch (GatewayFailure $e) {
            throw GatewayFailure::refundFailed($e->getMessage());
        }

        if (! is_string($refund['id'] ?? null)) {
            throw GatewayFailure::refundFailed('réponse inattendue');
        }

        return new GatewayRefund($refund['id']);
    }

    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '' || $this->webhookSecret === null || $this->webhookSecret === '') {
            return false;
        }

        $timestamp = null;
        $candidates = [];

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $candidates[] = $value;
            }
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || $candidates === []) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->webhookSecret);

        foreach ($candidates as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(string $rawBody): WebhookEvent
    {
        $event = json_decode($rawBody, true);
        $type = is_array($event) ? ($event['type'] ?? null) : null;
        $object = is_array($event) ? ($event['data']['object'] ?? null) : null;

        if (! is_string($event['id'] ?? null) || ! is_string($type) || ! is_array($object) || ! is_string($object['id'] ?? null)) {
            throw InvalidWebhook::malformed();
        }

        $succeeded = match ($type) {
            // « unpaid » : moyen de paiement différé, la confirmation viendra avec async_payment_succeeded.
            'checkout.session.completed' => ($object['payment_status'] ?? null) === 'paid' ? true : throw IgnoredWebhook::unhandledType($type.' (impayé)'),
            'checkout.session.async_payment_succeeded' => true,
            'checkout.session.async_payment_failed', 'checkout.session.expired' => false,
            default => throw IgnoredWebhook::unhandledType($type),
        };

        if (! is_int($object['amount_total'] ?? null) || ! is_string($object['currency'] ?? null)) {
            throw InvalidWebhook::malformed();
        }

        return new WebhookEvent(
            eventId: $event['id'],
            providerReference: $object['id'],
            succeeded: $succeeded,
            amountMinor: $object['amount_total'],
            currency: strtoupper($object['currency']),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, string $idempotencyKey): array
    {
        return $this->decode(fn () => $this->client()->withHeaders(['Idempotency-Key' => $idempotencyKey])->asForm()->post($this->apiBase.$path, $payload));
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        return $this->decode(fn () => $this->client()->get($this->apiBase.$path));
    }

    private function client(): PendingRequest
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw GatewayFailure::notConfigured('stripe');
        }

        return Http::withToken($this->apiKey)->acceptJson()->timeout(15);
    }

    /**
     * @param  callable(): Response  $call
     * @return array<string, mixed>
     */
    private function decode(callable $call): array
    {
        try {
            $response = $call();
        } catch (ConnectionException) {
            throw GatewayFailure::chargeFailed('Stripe injoignable');
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body)) {
            // Message Stripe lisible, jamais la clé ni la requête.
            throw GatewayFailure::chargeFailed(is_array($body) ? (string) ($body['error']['message'] ?? "erreur {$response->status()}") : "erreur {$response->status()}");
        }

        return $body;
    }
}
