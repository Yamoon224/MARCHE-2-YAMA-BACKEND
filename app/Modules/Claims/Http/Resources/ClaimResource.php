<?php

declare(strict_types=1);

namespace App\Modules\Claims\Http\Resources;

use App\Modules\Claims\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ClaimItem', required: ['id', 'order_line_id', 'name', 'issue', 'quantity', 'claimable_minor'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'order_line_id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'issue', type: 'string', enum: ['missing', 'damaged', 'expired', 'non_conforming']),
    new OA\Property(property: 'quantity', type: 'integer', description: 'Pièces, grammes ou millilitres'),
    new OA\Property(property: 'claimable_minor', type: 'integer', description: 'Plafond remboursable pour cette ligne'),
    new OA\Property(property: 'note', type: 'string', nullable: true),
])]
#[OA\Schema(schema: 'ClaimPhoto', required: ['id', 'url'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'url', type: 'string', description: 'Route autorisée qui sert la photo'),
    new OA\Property(property: 'mime', type: 'string'),
])]
#[OA\Schema(schema: 'Claim', required: ['id', 'order_id', 'status', 'claimable_minor', 'refund_amount_minor', 'refund_completed', 'items', 'photos'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'order_id', type: 'integer'),
    new OA\Property(property: 'status', type: 'string', enum: ['open', 'accepted', 'rejected']),
    new OA\Property(property: 'description', type: 'string', nullable: true),
    new OA\Property(property: 'claimable_minor', type: 'integer', description: 'Plafond remboursable de toute la réclamation'),
    new OA\Property(property: 'resolution', type: 'string', nullable: true, enum: ['refund', 'redelivery']),
    new OA\Property(property: 'cost_bearer', type: 'string', nullable: true, enum: ['supplier', 'preparation', 'delivery', 'store']),
    new OA\Property(property: 'refund_amount_minor', type: 'integer'),
    new OA\Property(property: 'refund_completed', type: 'boolean', description: 'Faux tant que le remboursement accordé n\'est pas parti'),
    new OA\Property(property: 'decision_note', type: 'string', nullable: true),
    new OA\Property(property: 'decided_at', type: 'string', nullable: true),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/ClaimItem')),
    new OA\Property(property: 'photos', type: 'array', items: new OA\Items(ref: '#/components/schemas/ClaimPhoto')),
])]
/** @property Claim $resource */
class ClaimResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $claim = $this->resource;
        $claim->loadMissing(['items', 'photos']);

        return [
            'id' => $claim->id,
            'order_id' => $claim->order_id,
            'status' => $claim->status->value,
            'description' => $claim->description,
            'claimable_minor' => $claim->claimableMinor(),
            'resolution' => $claim->resolution?->value,
            'cost_bearer' => $claim->cost_bearer?->value,
            'refund_amount_minor' => $claim->refund_amount_minor,
            'refund_completed' => $claim->refund_completed,
            'decision_note' => $claim->decision_note,
            'decided_at' => $claim->decided_at?->toIso8601String(),
            'items' => $claim->items->map(fn ($i) => [
                'id' => $i->id, 'order_line_id' => $i->order_line_id, 'name' => $i->name, 'issue' => $i->issue->value,
                'quantity' => $i->quantity, 'claimable_minor' => $i->claimable_minor, 'note' => $i->note,
            ])->all(),
            'photos' => $claim->photos->map(fn ($p) => [
                'id' => $p->id, 'url' => "/api/v1/claims/{$claim->id}/photos/{$p->id}", 'mime' => $p->mime,
            ])->all(),
        ];
    }
}
