<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Resources;

use App\Modules\Delivery\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'DeliveryZone', required: ['id', 'name', 'fee_minor', 'min_order_minor'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'fee_minor', type: 'integer'),
    new OA\Property(property: 'free_above_minor', type: 'integer', nullable: true),
    new OA\Property(property: 'min_order_minor', type: 'integer'),
])]
/** @property DeliveryZone $resource */
class ZoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $zone = $this->resource;

        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'fee_minor' => $zone->fee_minor,
            'free_above_minor' => $zone->free_above_minor,
            'min_order_minor' => $zone->min_order_minor,
        ];
    }
}
