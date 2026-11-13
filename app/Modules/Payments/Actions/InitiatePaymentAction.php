<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\PaymentNotAllowed;
use App\Modules\Payments\Gateways\GatewayRegistry;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Str;

final class InitiatePaymentAction
{
    public function __construct(
        private readonly OrderPaymentPort $orders,
        private readonly GatewayRegistry $gateways,
    ) {}

    /**
     * Le montant vient toujours de la commande, jamais du client. Un rejeu avec la même clé
     * d'idempotence retourne le paiement existant sans nouvel appel à l'agrégateur.
     *
     * @throws PaymentNotAllowed
     */
    public function execute(int $userId, int $orderId, PaymentMethod $method, ?string $idempotencyKey = null): Payment
    {
        $order = $this->orders->findPayable($orderId);

        // Même réponse pour « inexistante » et « à un autre client » : pas d'énumération possible.
        if ($order === null || $order->userId !== $userId) {
            throw PaymentNotAllowed::orderNotFound();
        }

        if ($idempotencyKey !== null) {
            $existing = Payment::where('order_id', $orderId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        if (! $order->acceptsPayment) {
            throw PaymentNotAllowed::orderNotPayable($order->status);
        }

        $reference = 'PAY-'.Str::upper((string) Str::ulid());

        if (! $method->isOnline()) {
            return Payment::create([
                'reference' => $reference, 'order_id' => $orderId, 'provider' => 'cod', 'method' => $method,
                'status' => PaymentStatus::PendingOnDelivery, 'amount_minor' => $order->totalMinor,
                'currency' => $order->currency, 'idempotency_key' => $idempotencyKey,
            ]);
        }

        $gateway = $this->gateways->default();
        $charge = $gateway->charge($reference, $order->totalMinor, $order->currency, $method);

        return Payment::create([
            'reference' => $reference, 'order_id' => $orderId, 'provider' => $gateway->name(),
            'provider_reference' => $charge->providerReference, 'method' => $method,
            'status' => PaymentStatus::Pending, 'amount_minor' => $order->totalMinor,
            'currency' => $order->currency, 'idempotency_key' => $idempotencyKey,
        ]);
    }
}
