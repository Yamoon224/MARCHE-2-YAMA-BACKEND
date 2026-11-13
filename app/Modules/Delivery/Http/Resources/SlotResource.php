<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Resources;

use App\Modules\Delivery\Models\DeliverySlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'DeliverySlot', required: ['id', 'starts_at', 'ends_at', 'remaining'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'delivery_zone_id', type: 'integer'),
    new OA\Property(property: 'starts_at', type: 'string'),
    new OA\Property(property: 'ends_at', type: 'string'),
    new OA\Property(property: 'remaining', type: 'integer', description: 'Places restantes'),
])]
/** @property DeliverySlot $resource */
class SlotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $slot = $this->resource;

        return [
            'id' => $slot->id,
            'delivery_zone_id' => $slot->delivery_zone_id,
            'starts_at' => $slot->starts_at->toIso8601String(),
            'ends_at' => $slot->ends_at->toIso8601String(),
            'remaining' => $slot->remaining(),
        ];
    }
}
