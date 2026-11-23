<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Resources;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Services\DeliveryViewBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Delivery', required: ['id', 'order_id', 'status', 'cash_due_minor'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'order_id', type: 'string', format: 'uuid'),
    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'assigned', 'out_for_delivery', 'delivered', 'failed', 'returned']),
    new OA\Property(property: 'courier_id', type: 'integer', nullable: true),
    new OA\Property(property: 'courier_name', type: 'string', nullable: true),
    new OA\Property(property: 'phone', type: 'string', nullable: true),
    new OA\Property(property: 'address', type: 'object', nullable: true, properties: [
        new OA\Property(property: 'neighborhood', type: 'string'),
        new OA\Property(property: 'landmark', type: 'string', nullable: true),
        new OA\Property(property: 'latitude', type: 'number', nullable: true),
        new OA\Property(property: 'longitude', type: 'number', nullable: true),
    ]),
    new OA\Property(property: 'slot_starts_at', type: 'string', nullable: true),
    new OA\Property(property: 'slot_ends_at', type: 'string', nullable: true),
    new OA\Property(property: 'cash_due_minor', type: 'integer', description: 'Espèces à encaisser à la remise (0 si déjà payée)'),
    new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
    new OA\Property(property: 'code_locked', type: 'boolean', description: 'Trop de codes erronés : un responsable doit débloquer'),
])]
/** @property Delivery $resource */
class DeliveryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return app(DeliveryViewBuilder::class)->build($this->resource);
    }
}
