<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Contracts\CustomerOrderStats;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Http\Resources\CustomerResource;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CustomerController
{
    public function __construct(private readonly CustomerOrderStats $stats) {}

    #[OA\Get(
        path: '/api/v1/customers',
        summary: 'Liste des clients avec nombre de commandes et total dépensé (permission customers.view)',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', description: 'Recherche nom, e-mail ou téléphone', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'guest', in: 'query', description: 'true : invités seulement ; false : comptes seulement', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Clients paginés', content: new OA\JsonContent(ref: '#/components/schemas/CustomerPage')),
            new OA\Response(response: 403, description: 'Interdit'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = trim($request->string('q')->toString());

        $page = User::query()
            ->where('role', Role::Customer->value)
            ->when($request->has('guest'), fn ($query) => $query->where('is_guest', $request->boolean('guest')))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)
                    ->orWhere('contact_email', 'like', $like)->orWhere('contact_phone', 'like', $like);
            }))
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        /** @var list<int> $ids */
        $ids = $page->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stats = $this->stats->statsFor($ids);

        foreach ($page->getCollection() as $user) {
            $this->withStats($user, $stats[$user->id] ?? null);
        }

        return CustomerResource::collection($page);
    }

    #[OA\Get(
        path: '/api/v1/customers/{user}',
        summary: 'Fiche d\'un client',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Client', content: new OA\JsonContent(ref: '#/components/schemas/CustomerResponse')),
            new OA\Response(response: 404, description: 'Introuvable'),
        ],
    )]
    public function show(User $user): CustomerResource
    {
        abort_unless($user->role === Role::Customer, 404);

        return new CustomerResource($this->withStats($user, $this->stats->statsFor([$user->id])[$user->id] ?? null));
    }

    /** @param  array{orders_count: int, total_spent_minor: int}|null  $stats */
    private function withStats(User $user, ?array $stats): User
    {
        $user->setAttribute('orders_count', $stats['orders_count'] ?? 0);
        $user->setAttribute('total_spent_minor', $stats['total_spent_minor'] ?? 0);

        return $user;
    }
}
