<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Actions;

use App\Modules\Invoicing\Events\CreditNoteIssued;
use App\Modules\Invoicing\Models\CreditNote;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Services\DocumentNumberer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class GenerateCreditNoteAction
{
    public function __construct(private readonly DocumentNumberer $numberer) {}

    /** Montant encore créditable sur la facture d'une commande : facturé - déjà crédité. */
    public function creditable(int $orderId): int
    {
        $invoice = Invoice::where('order_id', $orderId)->first();

        return $invoice === null ? 0 : max(0, $invoice->total_minor - (int) CreditNote::where('invoice_id', $invoice->id)->sum('total_minor'));
    }

    /**
     * Émet un avoir sur la facture d'une commande. Idempotent par `$sourceKey` ; le montant est plafonné
     * à ce qui reste créditable (on ne crédite jamais plus que facturé). La TVA est répartie au prorata de la facture.
     * Retourne null s'il n'y a pas de facture ou plus rien à créditer.
     */
    public function execute(int $orderId, int $amountMinor, string $reason, string $sourceKey): ?CreditNote
    {
        $existing = CreditNote::where('source_key', $sourceKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $invoice = Invoice::where('order_id', $orderId)->first();
        if ($invoice === null || $amountMinor <= 0) {
            return null;
        }

        try {
            $note = DB::transaction(function () use ($invoice, $amountMinor, $reason, $sourceKey) {
                // Verrou sur la facture : deux avoirs simultanés ne peuvent pas dépasser le montant facturé.
                Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                $credited = (int) CreditNote::where('invoice_id', $invoice->id)->sum('total_minor');
                $amount = min($amountMinor, $invoice->total_minor - $credited);

                if ($amount <= 0) {
                    return null;
                }

                $vat = intdiv($invoice->vat_minor * $amount + intdiv($invoice->total_minor, 2), $invoice->total_minor);

                return CreditNote::create([
                    'number' => $this->numberer->next('credit_note', 'AV'), 'invoice_id' => $invoice->id,
                    'order_id' => $invoice->order_id, 'source_key' => $sourceKey, 'issued_at' => now(),
                    'currency' => $invoice->currency, 'total_minor' => $amount, 'vat_minor' => $vat,
                    'net_minor' => $amount - $vat, 'reason' => $reason,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return CreditNote::where('source_key', $sourceKey)->first();
        }

        if ($note !== null) {
            CreditNoteIssued::dispatch(
                $note->id, $note->order_id, $note->number, $note->total_minor, $note->net_minor, $note->vat_minor, $note->currency,
            );
        }

        return $note;
    }
}
