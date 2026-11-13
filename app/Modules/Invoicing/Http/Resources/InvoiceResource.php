<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Http\Resources;

use App\Modules\Invoicing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'InvoiceLine', required: ['name', 'quantity', 'line_total_minor'], properties: [
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'sku', type: 'string', nullable: true),
    new OA\Property(property: 'quantity', type: 'integer'),
    new OA\Property(property: 'unit_price_minor', type: 'integer'),
    new OA\Property(property: 'line_total_minor', type: 'integer'),
    new OA\Property(property: 'vat_rate_bp', type: 'integer'),
    new OA\Property(property: 'vat_minor', type: 'integer'),
])]
#[OA\Schema(schema: 'Invoice', required: ['number', 'order_id', 'total_minor', 'vat_minor', 'net_minor'], properties: [
    new OA\Property(property: 'number', type: 'string', example: 'FAC-2026-000001'),
    new OA\Property(property: 'order_id', type: 'integer'),
    new OA\Property(property: 'issued_at', type: 'string'),
    new OA\Property(property: 'currency', type: 'string'),
    new OA\Property(property: 'total_minor', type: 'integer', description: 'Total TTC'),
    new OA\Property(property: 'vat_minor', type: 'integer'),
    new OA\Property(property: 'net_minor', type: 'integer', description: 'Total HT'),
    new OA\Property(property: 'credited_minor', type: 'integer', description: 'Total des avoirs émis sur cette facture'),
    new OA\Property(property: 'credit_notes', type: 'array', items: new OA\Items(ref: '#/components/schemas/CreditNote')),
    new OA\Property(property: 'lines', type: 'array', items: new OA\Items(ref: '#/components/schemas/InvoiceLine')),
])]
#[OA\Schema(schema: 'CreditNote', required: ['number', 'total_minor', 'reason'], properties: [
    new OA\Property(property: 'number', type: 'string', example: 'AV-2026-000001'),
    new OA\Property(property: 'issued_at', type: 'string'),
    new OA\Property(property: 'total_minor', type: 'integer'),
    new OA\Property(property: 'vat_minor', type: 'integer'),
    new OA\Property(property: 'net_minor', type: 'integer'),
    new OA\Property(property: 'reason', type: 'string'),
])]
/** @property Invoice $resource */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invoice = $this->resource;

        return [
            'number' => $invoice->number,
            'order_id' => $invoice->order_id,
            'issued_at' => $invoice->issued_at->toIso8601String(),
            'currency' => $invoice->currency,
            'total_minor' => $invoice->total_minor,
            'vat_minor' => $invoice->vat_minor,
            'net_minor' => $invoice->net_minor,
            'credited_minor' => $invoice->creditNotes->sum('total_minor'),
            'credit_notes' => $invoice->creditNotes->map(fn ($n) => [
                'number' => $n->number, 'issued_at' => $n->issued_at->toIso8601String(), 'total_minor' => $n->total_minor,
                'vat_minor' => $n->vat_minor, 'net_minor' => $n->net_minor, 'reason' => $n->reason,
            ])->all(),
            'lines' => $invoice->lines->map(fn ($l) => [
                'name' => $l->name, 'sku' => $l->sku, 'quantity' => $l->quantity,
                'unit_price_minor' => $l->unit_price_minor, 'line_total_minor' => $l->line_total_minor,
                'vat_rate_bp' => $l->vat_rate_bp, 'vat_minor' => $l->vat_minor,
            ])->all(),
        ];
    }
}
