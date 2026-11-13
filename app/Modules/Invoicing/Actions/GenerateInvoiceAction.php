<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Actions;

use App\Modules\Invoicing\Domain\VatCalculator;
use App\Modules\Invoicing\Events\InvoiceIssued;
use App\Modules\Invoicing\Exceptions\OrderNotInvoiceable;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Services\DocumentNumberer;
use App\Modules\Orders\Contracts\OrderInvoiceReader;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class GenerateInvoiceAction
{
    public function __construct(
        private readonly OrderInvoiceReader $orders,
        private readonly VatCalculator $vat,
        private readonly DocumentNumberer $numberer,
    ) {}

    /**
     * Émet la facture d'une commande, une seule fois (idempotent). La numérotation est séquentielle
     * et sans trou : le compteur est verrouillé dans la même transaction que la facture.
     *
     * @throws OrderNotInvoiceable
     */
    public function execute(int $orderId): Invoice
    {
        $existing = Invoice::where('order_id', $orderId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $order = $this->orders->find($orderId) ?? throw OrderNotInvoiceable::notFound($orderId);

        try {
            $invoice = DB::transaction(function () use ($order) {
                $number = $this->numberer->next('invoice', 'FAC');

                $lines = [];
                $vatTotal = 0;
                foreach ($order->lines as $line) {
                    $vat = $this->vat->vatIncluded($line->lineTotalMinor, $line->vatRateBp);
                    $vatTotal += $vat;
                    $lines[] = [
                        'product_id' => $line->productId, 'sku' => $line->sku, 'name' => $line->name,
                        'sale_unit' => $line->saleUnit, 'quantity' => $line->quantity,
                        'unit_price_minor' => $line->unitPriceMinor, 'line_total_minor' => $line->lineTotalMinor,
                        'vat_rate_bp' => $line->vatRateBp, 'vat_minor' => $vat,
                    ];
                }

                // Frais de livraison : ligne dédiée pour que le détail égale le total (TVA non appliquée tant que la règle fiscale n'est pas définie).
                if ($order->deliveryFeeMinor > 0) {
                    $lines[] = [
                        'product_id' => null, 'sku' => null, 'name' => 'Frais de livraison', 'sale_unit' => null,
                        'quantity' => 1, 'unit_price_minor' => $order->deliveryFeeMinor,
                        'line_total_minor' => $order->deliveryFeeMinor, 'vat_rate_bp' => 0, 'vat_minor' => 0,
                    ];
                }

                $invoice = Invoice::create([
                    'number' => $number, 'order_id' => $order->orderId, 'user_id' => $order->userId,
                    'issued_at' => now(), 'currency' => $order->currency,
                    'delivery_fee_minor' => $order->deliveryFeeMinor, 'discount_minor' => $order->discountMinor,
                    'total_minor' => $order->totalMinor, 'vat_minor' => $vatTotal,
                    'net_minor' => $order->totalMinor - $vatTotal,
                ]);
                $invoice->lines()->createMany($lines);

                return $invoice;
            });
        } catch (UniqueConstraintViolationException) {
            // Émise en parallèle par un autre processus : la transaction (et son numéro) a été annulée.
            return Invoice::where('order_id', $orderId)->firstOrFail();
        }

        InvoiceIssued::dispatch(
            $invoice->id, $invoice->order_id, $invoice->number, $invoice->total_minor,
            $invoice->net_minor, $invoice->vat_minor, $invoice->currency,
        );

        return $invoice;
    }
}
