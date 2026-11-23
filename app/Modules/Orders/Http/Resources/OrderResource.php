<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'OrderLine', required: ['id', 'product_id', 'name', 'quantity', 'unit_price_minor', 'line_total_minor'], properties: [
    new OA\Property(property: 'id', type: 'integer', description: 'Identifiant de la ligne (nécessaire pour déclarer une réclamation)'),
    new OA\Property(property: 'product_id', type: 'integer'),
    new OA\Property(property: 'sku', type: 'string'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'sale_unit', type: 'string'),
    new OA\Property(property: 'quantity', type: 'integer', description: 'Unité de base : pièces, grammes ou millilitres'),
    new OA\Property(property: 'unit_price_minor', type: 'integer'),
    new OA\Property(property: 'line_total_minor', type: 'integer'),
    new OA\Property(property: 'received_quantity', type: 'integer', nullable: true, description: 'Quantité réellement servie après préparation (null tant que non préparée)'),
])]
#[OA\Schema(schema: 'OrderHistoryEntry', required: ['to_status'], properties: [
    new OA\Property(property: 'from_status', type: 'string', nullable: true),
    new OA\Property(property: 'to_status', type: 'string'),
    new OA\Property(property: 'reason', type: 'string', nullable: true),
    new OA\Property(property: 'user_id', type: 'integer', nullable: true),
    new OA\Property(property: 'created_at', type: 'string', nullable: true),
])]
#[OA\Schema(schema: 'Order', required: ['id', 'status', 'total_minor'], properties: [
    new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Identifiant public (UUID)'),
    new OA\Property(property: 'number', type: 'string', nullable: true),
    new OA\Property(property: 'status', type: 'string', enum: [
        'draft', 'awaiting_payment', 'payment_failed', 'paid', 'under_review', 'confirmed', 'preparing', 'ready',
        'assigned', 'out_for_delivery', 'delivery_failed', 'delivered', 'completed', 'claim', 'refunded', 'returned', 'cancelled',
    ]),
    new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
    new OA\Property(property: 'subtotal_minor', type: 'integer'),
    new OA\Property(property: 'delivery_fee_minor', type: 'integer'),
    new OA\Property(property: 'discount_minor', type: 'integer'),
    new OA\Property(property: 'total_minor', type: 'integer'),
    new OA\Property(property: 'final_total_minor', type: 'integer', nullable: true, description: 'Montant réel après préparation'),
    new OA\Property(property: 'adjustment_minor', type: 'integer', description: 'Réel - payé : négatif = à rembourser'),
    new OA\Property(property: 'fulfillment', type: 'string', enum: ['pickup', 'delivery']),
    new OA\Property(property: 'delivery_slot_id', type: 'integer', nullable: true),
    new OA\Property(property: 'channel', type: 'string', nullable: true, description: 'online ou pos (vente en caisse)'),
    new OA\Property(property: 'contact_phone', type: 'string', nullable: true),
    new OA\Property(property: 'delivery_address', type: 'object', nullable: true, description: 'Adresse de livraison (quartier, repère, coordonnées)'),
    new OA\Property(property: 'created_at', type: 'string', nullable: true),
    new OA\Property(property: 'lines', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderLine')),
    new OA\Property(property: 'history', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderHistoryEntry')),
])]
#[OA\Schema(schema: 'OrderPage', required: ['data', 'meta'], properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
    new OA\Property(property: 'meta', type: 'object', required: ['current_page', 'last_page', 'total'], properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
    ]),
])]
/** @property Order $resource */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        return [
            'id' => $order->uuid,
            'number' => $order->number,
            'status' => $order->status->value,
            'currency' => $order->currency,
            'subtotal_minor' => $order->subtotal_minor,
            'delivery_fee_minor' => $order->delivery_fee_minor,
            'discount_minor' => $order->discount_minor,
            'total_minor' => $order->total_minor,
            'final_total_minor' => $order->final_total_minor,
            'adjustment_minor' => $order->adjustment_minor,
            'fulfillment' => $order->fulfillment,
            'delivery_slot_id' => $order->delivery_slot_id,
            'channel' => $order->channel,
            'contact_phone' => $order->contact_phone,
            'delivery_address' => $order->delivery_address,
            'created_at' => $order->created_at?->toIso8601String(),
            'lines' => $order->relationLoaded('lines') ? $order->lines->map(fn ($l) => [
                'id' => $l->id, 'received_quantity' => $l->final_quantity,
                'product_id' => $l->product_id, 'sku' => $l->sku, 'name' => $l->name, 'sale_unit' => $l->sale_unit,
                'quantity' => $l->quantity, 'unit_price_minor' => $l->unit_price_minor, 'line_total_minor' => $l->line_total_minor,
            ])->all() : [],
            'history' => $order->relationLoaded('history') ? $order->history->map(fn ($h) => [
                'from_status' => $h->from_status, 'to_status' => $h->to_status, 'reason' => $h->reason,
                // Suivi public : jamais l'identifiant interne du personnel.
                'user_id' => $request->is('api/v1/orders/lookup') ? null : $h->user_id, 'created_at' => $h->created_at?->toIso8601String(),
            ])->all() : [],
        ];
    }
}
