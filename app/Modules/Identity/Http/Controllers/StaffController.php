<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Http\Requests\StaffRequest;
use App\Modules\Identity\Http\Resources\StaffResource;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class StaffController
{
    #[OA\Get(
        path: '/api/v1/staff',
        summary: 'Liste du personnel (permission users.manage)',
        security: [['sanctum' => []]],
        tags: ['Personnel'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', description: 'Recherche nom, e-mail ou téléphone', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Personnel paginé', content: new OA\JsonContent(ref: '#/components/schemas/StaffPage')),
            new OA\Response(response: 403, description: 'Interdit'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = trim($request->string('q')->toString());
        $role = Role::tryFrom($request->string('role')->toString());

        $members = User::query()
            ->where('role', '!=', Role::Customer->value)
            ->when($role !== null, fn ($query) => $query->where('role', $role?->value))
            ->when($request->has('active'), fn ($query) => $request->boolean('active')
                ? $query->whereNull('deactivated_at')
                : $query->whereNotNull('deactivated_at'))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like);
            }))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return StaffResource::collection($members);
    }

    #[OA\Post(
        path: '/api/v1/staff',
        summary: 'Créer un compte du personnel',
        security: [['sanctum' => []]],
        tags: ['Personnel'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'role', 'password'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'role', type: 'string', example: 'picker'),
                new OA\Property(property: 'password', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé', content: new OA\JsonContent(ref: '#/components/schemas/StaffMemberResponse')),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function store(StaffRequest $request): JsonResponse
    {
        $member = new User($request->safe()->only(['name', 'email', 'phone', 'password']));
        $member->role = Role::from($request->string('role')->toString());
        $member->save();

        return (new StaffResource($member))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/v1/staff/{user}',
        summary: 'Détail d\'un membre du personnel',
        security: [['sanctum' => []]],
        tags: ['Personnel'],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Membre', content: new OA\JsonContent(ref: '#/components/schemas/StaffMemberResponse')),
            new OA\Response(response: 404, description: 'Introuvable'),
        ],
    )]
    public function show(User $user): StaffResource
    {
        return new StaffResource($this->staffOrFail($user));
    }

    #[OA\Put(
        path: '/api/v1/staff/{user}',
        summary: 'Modifier un membre du personnel (nom, contacts, rôle, mot de passe)',
        security: [['sanctum' => []]],
        tags: ['Personnel'],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'phone', type: 'string', nullable: true),
            new OA\Property(property: 'role', type: 'string'),
            new OA\Property(property: 'password', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Compte mis à jour', content: new OA\JsonContent(ref: '#/components/schemas/StaffMemberResponse')),
            new OA\Response(response: 404, description: 'Introuvable'),
            new OA\Response(response: 422, description: 'Validation, ou changement de rôle interdit (son propre rôle, dernier administrateur)'),
        ],
    )]
    public function update(StaffRequest $request, User $user): StaffResource
    {
        $member = $this->staffOrFail($user);
        $newRole = $request->has('role') ? Role::from($request->string('role')->toString()) : $member->role;

        if ($newRole !== $member->role) {
            if ($member->is($request->user())) {
                throw ValidationException::withMessages(['role' => ['Vous ne pouvez pas modifier votre propre rôle.']]);
            }
            $this->guardLastAdmin($member, 'role');
        }

        $member->fill($request->safe()->only(['name', 'email', 'phone', 'password']));
        $member->role = $newRole;
        $member->save();

        return new StaffResource($member);
    }

    #[OA\Delete(
        path: '/api/v1/staff/{user}',
        summary: 'Désactiver un compte (connexion impossible, jetons révoqués ; pas de suppression)',
        security: [['sanctum' => []]],
        tags: ['Personnel'],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Compte désactivé', content: new OA\JsonContent(ref: '#/components/schemas/StaffMemberResponse')),
            new OA\Response(response: 404, description: 'Introuvable'),
            new OA\Response(response: 422, description: 'Son propre compte ou dernier administrateur'),
        ],
    )]
    public function destroy(Request $request, User $user): StaffResource
    {
        $member = $this->staffOrFail($user);

        if ($member->is($request->user())) {
            throw ValidationException::withMessages(['user' => ['Vous ne pouvez pas désactiver votre propre compte.']]);
        }
        $this->guardLastAdmin($member, 'user');

        $member->forceFill(['deactivated_at' => now()])->save();
        $member->tokens()->delete();

        return new StaffResource($member);
    }

    #[OA\Post(
        path: '/api/v1/staff/{user}/reactivate',
        summary: 'Réactiver un compte désactivé',
        security: [['sanctum' => []]],
        tags: ['Personnel'],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Compte réactivé', content: new OA\JsonContent(ref: '#/components/schemas/StaffMemberResponse')),
            new OA\Response(response: 404, description: 'Introuvable'),
        ],
    )]
    public function reactivate(User $user): StaffResource
    {
        $member = $this->staffOrFail($user);
        $member->forceFill(['deactivated_at' => null])->save();

        return new StaffResource($member);
    }

    private function staffOrFail(User $user): User
    {
        abort_unless($user->role->isStaff(), 404);

        return $user;
    }

    /** Il doit toujours rester un administrateur actif. */
    private function guardLastAdmin(User $member, string $field): void
    {
        if ($member->role !== Role::Admin || ! $member->isActive()) {
            return;
        }

        $others = User::where('role', Role::Admin->value)->whereNull('deactivated_at')->whereKeyNot($member->getKey())->count();

        if ($others === 0) {
            throw ValidationException::withMessages([$field => ['Il doit rester au moins un administrateur actif.']]);
        }
    }
}
