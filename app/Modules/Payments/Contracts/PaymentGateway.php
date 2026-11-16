<?php

declare(strict_types=1);

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\DTO\GatewayCharge;
use App\Modules\Payments\DTO\GatewayRefund;
use App\Modules\Payments\DTO\WebhookEvent;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Exceptions\GatewayFailure;
use App\Modules\Payments\Exceptions\IgnoredWebhook;
use App\Modules\Payments\Exceptions\InvalidWebhook;

/** Port vers un agrégateur de paiement ; toutes les implémentations sont interchangeables. */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Démarre un paiement en ligne. `$context` (ex. `order_id`) sert aux adresses de retour d'une page de paiement hébergée.
     *
     * @param  array<string, scalar>  $context
     *
     * @throws GatewayFailure
     */
    public function charge(string $reference, int $amountMinor, string $currency, PaymentMethod $method, array $context = []): GatewayCharge;

    /**
     * Rembourse tout ou partie d'un paiement. `$reference` identifie le remboursement chez l'agrégateur :
     * le rejouer ne doit pas rembourser deux fois.
     *
     * @throws GatewayFailure
     */
    public function refund(string $providerReference, int $amountMinor, string $currency, string $reference): GatewayRefund;

    public function verifySignature(string $rawBody, ?string $signature): bool;

    /**
     * @throws InvalidWebhook
     * @throws IgnoredWebhook
     */
    public function parseWebhook(string $rawBody): WebhookEvent;
}
