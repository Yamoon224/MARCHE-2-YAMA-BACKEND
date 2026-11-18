<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Enums\Role;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RoleController
{
    #[OA\Get(
        path: '/api/v1/roles',
        summary: 'Rôles attribuables au personnel, avec libellé et permissions (permission users.manage)',
        security: [['sanctum' => []]],
        tags: ['Personnel'],
        responses: [new OA\Response(response: 200, description: 'Rôles', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                required: ['value', 'label', 'permissions'],
                properties: [
                    new OA\Property(property: 'value', type: 'string'),
                    new OA\Property(property: 'label', type: 'string'),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), description: '« * » : toutes les permissions'),
                ],
            )),
        ]))],
    )]
    public function index(): JsonResponse
    {
        return response()->json(['data' => array_map(fn (Role $role) => [
            'value' => $role->value,
            'label' => $role->label(),
            'permissions' => $role->permissions(),
        ], Role::staffRoles())]);
    }
}
