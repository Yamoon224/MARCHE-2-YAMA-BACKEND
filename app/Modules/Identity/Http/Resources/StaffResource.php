<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'StaffMember', required: ['id', 'name', 'role', 'role_label', 'is_active'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'email', type: 'string', nullable: true),
    new OA\Property(property: 'phone', type: 'string', nullable: true),
    new OA\Property(property: 'role', type: 'string'),
    new OA\Property(property: 'role_label', type: 'string'),
    new OA\Property(property: 'is_active', type: 'boolean'),
    new OA\Property(property: 'deactivated_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
])]
#[OA\Schema(schema: 'StaffMemberResponse', required: ['data'], properties: [
    new OA\Property(property: 'data', ref: '#/components/schemas/StaffMember'),
])]
#[OA\Schema(schema: 'StaffPage', required: ['data', 'meta'], properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/StaffMember')),
    new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
    ]),
])]
/** @property User $resource */
class StaffResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'role' => $this->resource->role->value,
            'role_label' => $this->resource->role->label(),
            'is_active' => $this->resource->isActive(),
            'deactivated_at' => $this->resource->deactivated_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
