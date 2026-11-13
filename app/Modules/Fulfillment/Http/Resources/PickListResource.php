<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Http\Resources;

use App\Modules\Fulfillment\Models\PickList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PickLine', required: ['id', 'name', 'sale_unit', 'ordered_quantity', 'status', 'max_quantity'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'sale_unit', type: 'string', enum: ['piece', 'kg', 'l']),
    new OA\Property(property: 'ordered_quantity', type: 'integer', description: 'Pièces, grammes ou millilitres'),
    new OA\Property(property: 'max_quantity', type: 'integer', description: 'Plafond servable (commandé + marge de pesée)'),
    new OA\Property(property: 'substitution', type: 'string', enum: ['allow', 'refuse']),
    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'picked', 'short', 'substituted']),
    new OA\Property(property: 'picked_quantity', type: 'integer', nullable: true),
    new OA\Property(property: 'final_name', type: 'string', nullable: true),
    new OA\Property(property: 'final_unit_price_minor', type: 'integer', nullable: true),
])]
#[OA\Schema(schema: 'PickList', required: ['id', 'order_id', 'status', 'lines'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'order_id', type: 'integer'),
    new OA\Property(property: 'status', type: 'string', enum: ['open', 'in_progress', 'completed']),
    new OA\Property(property: 'picker_id', type: 'integer', nullable: true),
    new OA\Property(property: 'lines', type: 'array', items: new OA\Items(ref: '#/components/schemas/PickLine')),
])]
/** @property PickList $resource */
class PickListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $list = $this->resource;

        return [
            'id' => $list->id,
            'order_id' => $list->order_id,
            'status' => $list->status->value,
            'picker_id' => $list->picker_id,
            'lines' => $list->lines->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'sale_unit' => $l->sale_unit,
                'ordered_quantity' => $l->ordered_quantity,
                'max_quantity' => $l->maxQuantity(),
                'substitution' => $l->substitution,
                'status' => $l->status->value,
                'picked_quantity' => $l->picked_quantity,
                'final_name' => $l->final_name,
                'final_unit_price_minor' => $l->final_unit_price_minor,
            ])->all(),
        ];
    }
}
