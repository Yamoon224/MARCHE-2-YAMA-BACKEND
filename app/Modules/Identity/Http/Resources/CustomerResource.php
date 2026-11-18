<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Customer', required: ['id', 'name', 'is_guest', 'is_active', 'orders_count', 'total_spent_minor'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'email', type: 'string', nullable: true),
    new OA\Property(property: 'phone', type: 'string', nullable: true),
    new OA\Property(property: 'is_guest', type: 'boolean'),
    new OA\Property(property: 'is_active', type: 'boolean'),
    new OA\Property(property: 'orders_count', type: 'integer'),
    new OA\Property(property: 'total_spent_minor', type: 'integer', description: 'Total des commandes aboutties, en centimes'),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
])]
#[OA\Schema(schema: 'CustomerResponse', required: ['data'], properties: [
    new OA\Property(property: 'data', ref: '#/components/schemas/Customer'),
])]
#[OA\Schema(schema: 'CustomerPage', required: ['data', 'meta'], properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Customer')),
    new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
    ]),
])]
/** @property User $resource */
class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email ?? $this->resource->contact_email,
            'phone' => $this->resource->phone ?? $this->resource->contact_phone,
            'is_guest' => $this->resource->is_guest,
            'is_active' => $this->resource->isActive(),
            'orders_count' => (int) $this->resource->getAttribute('orders_count'),
            'total_spent_minor' => (int) $this->resource->getAttribute('total_spent_minor'),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
