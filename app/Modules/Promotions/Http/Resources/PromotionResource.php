<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Resources;

use App\Modules\Promotions\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Promotion', required: ['id', 'code', 'type', 'value', 'state', 'is_active', 'used_count'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'code', type: 'string'),
    new OA\Property(property: 'description', type: 'string', nullable: true),
    new OA\Property(property: 'type', type: 'string', enum: ['percent', 'fixed']),
    new OA\Property(property: 'value', type: 'integer', description: 'Pourcentage (1-100) ou centimes selon le type'),
    new OA\Property(property: 'min_order_minor', type: 'integer'),
    new OA\Property(property: 'max_uses', type: 'integer', nullable: true),
    new OA\Property(property: 'used_count', type: 'integer'),
    new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'is_active', type: 'boolean'),
    new OA\Property(property: 'state', type: 'string', enum: ['active', 'scheduled', 'expired', 'exhausted', 'disabled']),
])]
#[OA\Schema(schema: 'PromotionResponse', required: ['data'], properties: [
    new OA\Property(property: 'data', ref: '#/components/schemas/Promotion'),
])]
#[OA\Schema(schema: 'PromotionPage', required: ['data', 'meta'], properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Promotion')),
    new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
    ]),
])]
/** @property Promotion $resource */
class PromotionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'id' => $p->id,
            'code' => $p->code,
            'description' => $p->description,
            'type' => $p->type->value,
            'value' => $p->value,
            'min_order_minor' => $p->min_order_minor,
            'max_uses' => $p->max_uses,
            'used_count' => $p->used_count,
            'starts_at' => $p->starts_at?->toIso8601String(),
            'ends_at' => $p->ends_at?->toIso8601String(),
            'is_active' => $p->is_active,
            'state' => $p->state()->value,
        ];
    }
}
