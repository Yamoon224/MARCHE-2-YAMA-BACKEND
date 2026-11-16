<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'User', required: ['id', 'name', 'role'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'email', type: 'string', nullable: true),
    new OA\Property(property: 'phone', type: 'string', nullable: true),
    new OA\Property(property: 'role', type: 'string'),
    new OA\Property(property: 'is_guest', type: 'boolean', description: 'Client sans compte (commande invité)'),
])]
#[OA\Schema(schema: 'AuthResult', required: ['user', 'token'], properties: [
    new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    new OA\Property(property: 'token', type: 'string'),
])]
/** @property User $resource */
class UserResource extends JsonResource
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
            'is_guest' => $this->resource->is_guest,
        ];
    }
}
