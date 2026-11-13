<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Events\RefundIssued;
use App\Modules\Payments\Exceptions\GatewayFailure;
use App\Modules\Payments\Exceptions\RefundNotAllowed;
use App\Modules\Payments\Gateways\GatewayRegistry;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RefundPaymentAction
{
    public function __construct(private readonly GatewayRegistry $gateways) {}

    /** Montant encore remboursable pour une commande : encaissé - déjà remboursé (ou en cours). */
    public function refundable(int $orderId): int
    {
        return Payment::where('order_id', $orderId)->where('status', PaymentStatus::Succeeded)->get()
            ->sum(fn (Payment $p) => $this->refundableOf($p));
    }

    /**
     * Rembourse un montant sur les paiements encaissés d'une commande. Idempotent par `$sourceKey` :
     * un même fait déclencheur (annulation, écart de pesée, geste commercial) ne rembourse qu'une fois.
     *
     * `$strict` : refuse un montant supérieur au remboursable (gestes manuels). Sinon le montant est
     * plafonné (déclencheurs automatiques : le client ne peut pas recevoir plus qu'il n'a payé).
     * Un paiement à la livraison non encaissé n'a rien à rembourser.
     *
     * @return list<Refund>
     *
     * @throws RefundNotAllowed
     */
    public function execute(int $orderId, int $amountMinor, RefundKind $kind, string $reason, string $sourceKey, bool $strict = true): array
    {
        if ($amountMinor <= 0) {
            return [];
        }

        $existing = Refund::where('source_key', $sourceKey)->orWhere('source_key', 'like', $sourceKey.'#%')->get();
        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        try {
            $created = DB::transaction(function () use ($orderId, $amountMinor, $kind, $reason, $sourceKey, $strict) {
                $payments = Payment::where('order_id', $orderId)->where('status', PaymentStatus::Succeeded)
                    ->orderBy('id')->lockForUpdate()->get();

                $remaining = $amountMinor;
                $refunds = [];

                foreach ($payments as $payment) {
                    $take = min($remaining, $this->refundableOf($payment));
                    if ($take <= 0) {
                        continue;
                    }

                    $refunds[] = Refund::create([
                        'reference' => 'RF-'.Str::upper((string) Str::ulid()),
                        'payment_id' => $payment->id, 'order_id' => $orderId, 'amount_minor' => $take,
                        'currency' => $payment->currency, 'status' => RefundStatus::Pending, 'kind' => $kind,
                        'reason' => $reason, 'source_key' => count($refunds) === 0 ? $sourceKey : $sourceKey.'#'.count($refunds),
                    ]);
                    $remaining -= $take;
                }

                if ($strict && $remaining > 0) {
                    throw $payments->isEmpty()
                        ? RefundNotAllowed::nothingToRefund()
                        : RefundNotAllowed::exceedsRefundable($amountMinor - $remaining);
                }

                return $refunds;
            });
        } catch (UniqueConstraintViolationException) {
            // Même déclencheur traité en parallèle : l'autre processus a créé le remboursement.
            return Refund::where('source_key', $sourceKey)->orWhere('source_key', 'like', $sourceKey.'#%')->get()->all();
        }

        // L'appel à l'agrégateur se fait hors transaction : pas de verrou tenu pendant un appel réseau.
        foreach ($created as $refund) {
            $this->settle($refund);
        }

        return $created;
    }

    /** Envoie (ou renvoie) le remboursement à l'agrégateur. L'échec est consigné pour reprise, jamais perdu. */
    public function settle(Refund $refund): Refund
    {
        if ($refund->status === RefundStatus::Succeeded) {
            return $refund;
        }

        $payment = $refund->payment;

        try {
            $result = $this->gateways->get($payment->provider)->refund(
                (string) $payment->provider_reference, $refund->amount_minor, $refund->currency, $refund->reference,
            );
        } catch (GatewayFailure $e) {
            $refund->update(['status' => RefundStatus::Failed, 'failure' => $e->getMessage()]);

            return $refund;
        }

        $refund->update(['status' => RefundStatus::Succeeded, 'provider_reference' => $result->providerReference, 'failure' => null]);

        RefundIssued::dispatch($refund->id, $refund->order_id, $refund->amount_minor, $refund->currency, $payment->method, $refund->kind);

        return $refund;
    }

    private function refundableOf(Payment $payment): int
    {
        $refunded = (int) Refund::where('payment_id', $payment->id)->where('status', '!=', RefundStatus::Failed)->sum('amount_minor');

        return max(0, $payment->amount_minor - $refunded);
    }
}
