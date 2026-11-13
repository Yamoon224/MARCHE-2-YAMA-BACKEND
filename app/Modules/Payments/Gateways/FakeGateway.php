<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\DTO\GatewayCharge;
use App\Modules\Payments\DTO\GatewayRefund;
use App\Modules\Payments\DTO\WebhookEvent;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Exceptions\GatewayFailure;
use App\Modules\Payments\Exceptions\InvalidWebhook;
use Illuminate\Support\Str;

/**
 * Agrégateur simulé (développement, recette, tests) : webhooks signés en HMAC-SHA256,
 * même contrat qu'un vrai agrégateur de mobile money ou de carte.
 */
final class FakeGateway implements PaymentGateway
{
    public function __construct(private readonly string $secret) {}

    public function name(): string
    {
        return 'fake';
    }

    public function charge(string $reference, int $amountMinor, string $currency, PaymentMethod $method): GatewayCharge
    {
        return new GatewayCharge('fake_'.Str::lower((string) Str::ulid()));
    }

    public function refund(string $providerReference, int $amountMinor, string $currency, string $reference): GatewayRefund
    {
        // Simulateur : permet de rejouer le cas « l'agrégateur refuse » en recette.
        if ((bool) config('payments.gateways.fake.fail_refunds', false)) {
            throw GatewayFailure::refundFailed("simulation d'échec");
        }

        return new GatewayRefund('fake_rf_'.Str::lower((string) Str::ulid()));
    }

    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        return $signature !== null && hash_equals($this->sign($rawBody), $signature);
    }

    public function parseWebhook(string $rawBody): WebhookEvent
    {
        $data = json_decode($rawBody, true);

        if (! is_array($data)
            || ! is_string($data['event_id'] ?? null)
            || ! is_string($data['provider_reference'] ?? null)
            || ! in_array($data['status'] ?? null, ['succeeded', 'failed'], true)
            || ! is_int($data['amount_minor'] ?? null)
            || ! is_string($data['currency'] ?? null)) {
            throw InvalidWebhook::malformed();
        }

        return new WebhookEvent(
            eventId: $data['event_id'],
            providerReference: $data['provider_reference'],
            succeeded: $data['status'] === 'succeeded',
            amountMinor: $data['amount_minor'],
            currency: $data['currency'],
        );
    }

    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, $this->secret);
    }
}
