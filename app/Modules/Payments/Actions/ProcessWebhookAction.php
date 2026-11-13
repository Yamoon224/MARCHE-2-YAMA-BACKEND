<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Payments\DTO\WebhookEvent;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Enums\WebhookOutcome;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Payments\Exceptions\InvalidWebhook;
use App\Modules\Payments\Gateways\GatewayRegistry;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class ProcessWebhookAction
{
    public function __construct(
        private readonly GatewayRegistry $gateways,
        private readonly OrderPaymentPort $orders,
        private readonly RefundPaymentAction $refunds,
    ) {}

    /**
     * Traite un webhook signé : idempotent (un même événement n'a qu'un effet), insensible à l'ordre
     * d'arrivée (un paiement réussi n'est jamais écrasé) et vérifie montant et devise.
     *
     * @throws InvalidWebhook
     */
    public function execute(string $provider, string $rawBody, ?string $signature): WebhookOutcome
    {
        $gateway = $this->gateways->get($provider);

        if (! $gateway->verifySignature($rawBody, $signature)) {
            throw InvalidWebhook::badSignature();
        }

        $event = $gateway->parseWebhook($rawBody);
        $succeededPayment = null;

        try {
            $outcome = DB::transaction(function () use ($provider, $rawBody, $event, &$succeededPayment) {
                $record = PaymentWebhookEvent::create([
                    'provider' => $provider, 'event_id' => $event->eventId,
                    'outcome' => 'processing', 'payload' => json_decode($rawBody, true),
                ]);

                $payment = Payment::where('provider', $provider)
                    ->where('provider_reference', $event->providerReference)
                    ->lockForUpdate()
                    ->first();

                $outcome = $payment === null ? WebhookOutcome::Ignored : $this->apply($payment, $event);
                if ($outcome === WebhookOutcome::Succeeded) {
                    $succeededPayment = $payment;
                }

                $record->update(['outcome' => $outcome->value]);

                return $outcome;
            });
        } catch (UniqueConstraintViolationException) {
            return WebhookOutcome::Duplicate;
        }

        if ($succeededPayment !== null) {
            PaymentSucceeded::dispatch(
                $succeededPayment->id, $succeededPayment->order_id, $succeededPayment->amount_minor,
                $succeededPayment->currency, $succeededPayment->method,
            );

            // Argent reçu pour une commande qui n'est plus payable : on le rend aussitôt (hors transaction).
            if ($succeededPayment->needs_refund) {
                $this->refunds->execute(
                    $succeededPayment->order_id, $succeededPayment->amount_minor, RefundKind::LatePayment,
                    'Paiement reçu après annulation de la commande', "late:payment:{$succeededPayment->id}", strict: false,
                );
            }
        }

        return $outcome;
    }

    private function apply(Payment $payment, WebhookEvent $event): WebhookOutcome
    {
        // Un paiement déjà réussi ou signalé est terminal : on ne le rouvre pas (webhooks dans le désordre).
        if (in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::Flagged], true)) {
            return WebhookOutcome::Ignored;
        }

        if ($event->amountMinor !== $payment->amount_minor || $event->currency !== $payment->currency) {
            $payment->update([
                'status' => PaymentStatus::Flagged,
                'note' => "Montant ou devise inattendus : reçu {$event->amountMinor} {$event->currency}, attendu {$payment->amount_minor} {$payment->currency}.",
            ]);

            return WebhookOutcome::Flagged;
        }

        if (! $event->succeeded) {
            if ($payment->status === PaymentStatus::Pending) {
                $payment->update(['status' => PaymentStatus::Failed]);
                $this->orders->markPaymentFailed($payment->order_id);
            }

            return WebhookOutcome::Failed;
        }

        $payment->update(['status' => PaymentStatus::Succeeded]);

        if (! $this->orders->markPaid($payment->order_id)) {
            $payment->update([
                'needs_refund' => true,
                'note' => 'Paiement reçu pour une commande qui n\'est plus payable : remboursement à traiter.',
            ]);
        }

        return WebhookOutcome::Succeeded;
    }
}
