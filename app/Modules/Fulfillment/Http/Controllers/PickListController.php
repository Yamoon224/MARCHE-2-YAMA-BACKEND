<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Http\Controllers;

use App\Modules\Fulfillment\Actions\CompletePickListAction;
use App\Modules\Fulfillment\Actions\RecordPickAction;
use App\Modules\Fulfillment\Actions\StartPickListAction;
use App\Modules\Fulfillment\Actions\SubstituteLineAction;
use App\Modules\Fulfillment\Exceptions\PickingNotAllowed;
use App\Modules\Fulfillment\Http\Requests\PickRequest;
use App\Modules\Fulfillment\Http\Requests\SubstituteRequest;
use App\Modules\Fulfillment\Http\Resources\PickListResource;
use App\Modules\Fulfillment\Models\PickLine;
use App\Modules\Fulfillment\Models\PickList;
use App\Modules\Identity\Contracts\PermissionHolder;
use App\Modules\Orders\Exceptions\InsufficientStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PickListController
{
    #[OA\Get(
        path: '/api/v1/fulfillment/pick-lists',
        summary: 'File de préparation (ouvertes et en cours par défaut)',
        security: [['sanctum' => []]],
        tags: ['Préparation'],
        parameters: [new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['open', 'in_progress', 'completed']))],
        responses: [
            new OA\Response(response: 200, description: 'Listes de préparation', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PickList')),
            ])),
            new OA\Response(response: 403, description: 'Interdit'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->string('status')->toString();

        $lists = PickList::with('lines')
            ->when($status !== '', fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', ['open', 'in_progress']))
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        return PickListResource::collection($lists);
    }

    #[OA\Get(
        path: '/api/v1/fulfillment/pick-lists/{pickList}',
        summary: 'Détail d\'une préparation',
        security: [['sanctum' => []]],
        tags: ['Préparation'],
        parameters: [new OA\Parameter(name: 'pickList', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Liste de préparation', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PickList')])),
            new OA\Response(response: 404, description: 'Introuvable'),
        ],
    )]
    public function show(PickList $pickList): PickListResource
    {
        return new PickListResource($pickList->load('lines'));
    }

    #[OA\Post(
        path: '/api/v1/fulfillment/pick-lists/{pickList}/start',
        summary: 'Prendre en charge une préparation',
        security: [['sanctum' => []]],
        tags: ['Préparation'],
        parameters: [new OA\Parameter(name: 'pickList', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Préparation démarrée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PickList')])),
            new OA\Response(response: 422, description: 'Déjà prise ou terminée'),
        ],
    )]
    public function start(Request $request, PickList $pickList, StartPickListAction $action): PickListResource
    {
        $user = $this->user($request);

        return $this->guard(fn () => new PickListResource($action->execute($pickList, (int) $user->getAuthIdentifier())->load('lines')));
    }

    #[OA\Post(
        path: '/api/v1/fulfillment/pick-lists/{pickList}/lines/{line}/pick',
        summary: 'Enregistrer la quantité prélevée ou pesée (0 = rupture)',
        security: [['sanctum' => []]],
        tags: ['Préparation'],
        parameters: [
            new OA\Parameter(name: 'pickList', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'line', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['quantity'],
            properties: [new OA\Property(property: 'quantity', type: 'integer', description: 'Pièces, grammes ou millilitres')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Ligne enregistrée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PickList')])),
            new OA\Response(response: 403, description: 'Préparation d\'un autre préparateur'),
            new OA\Response(response: 422, description: 'Quantité refusée'),
        ],
    )]
    public function pick(PickRequest $request, PickList $pickList, PickLine $line, RecordPickAction $action): PickListResource
    {
        $this->ensureCanWork($request, $pickList);

        return $this->guard(function () use ($action, $line, $request, $pickList) {
            $action->execute($line, (int) $request->integer('quantity'));

            return new PickListResource($pickList->load('lines'));
        });
    }

    #[OA\Post(
        path: '/api/v1/fulfillment/pick-lists/{pickList}/lines/{line}/substitute',
        summary: 'Remplacer un article en rupture (prix plafonné au prix commandé)',
        security: [['sanctum' => []]],
        tags: ['Préparation'],
        parameters: [
            new OA\Parameter(name: 'pickList', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'line', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['product_id', 'quantity'],
            properties: [
                new OA\Property(property: 'product_id', type: 'integer'),
                new OA\Property(property: 'quantity', type: 'integer'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Remplacement enregistré', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PickList')])),
            new OA\Response(response: 422, description: 'Remplacement refusé par le client ou impossible'),
        ],
    )]
    public function substitute(SubstituteRequest $request, PickList $pickList, PickLine $line, SubstituteLineAction $action): PickListResource
    {
        $this->ensureCanWork($request, $pickList);

        return $this->guard(function () use ($action, $line, $request, $pickList) {
            $action->execute($line, (int) $request->integer('product_id'), (int) $request->integer('quantity'));

            return new PickListResource($pickList->load('lines'));
        });
    }

    #[OA\Post(
        path: '/api/v1/fulfillment/pick-lists/{pickList}/complete',
        summary: 'Terminer la préparation : totaux réels, stock ajusté, commande prête',
        security: [['sanctum' => []]],
        tags: ['Préparation'],
        parameters: [new OA\Parameter(name: 'pickList', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Préparation terminée', content: new OA\JsonContent(
                required: ['data', 'adjustment_minor'],
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/PickList'),
                    new OA\Property(property: 'adjustment_minor', type: 'integer', description: 'Réel - payé : négatif = à rembourser, positif = à encaisser'),
                ],
            )),
            new OA\Response(response: 422, description: 'Articles restants ou remplaçant indisponible'),
        ],
    )]
    public function complete(Request $request, PickList $pickList, CompletePickListAction $action): JsonResponse
    {
        $this->ensureCanWork($request, $pickList);
        $user = $this->user($request);

        $delta = $this->guard(fn () => $action->execute($pickList, (int) $user->getAuthIdentifier()));

        return response()->json([
            'data' => (new PickListResource($pickList->refresh()->load('lines')))->resolve(),
            'adjustment_minor' => $delta,
        ]);
    }

    private function user(Request $request): PermissionHolder
    {
        $user = $request->user();
        abort_unless($user instanceof PermissionHolder, 401);

        return $user;
    }

    /** Une préparation prise en charge n'est modifiable que par son préparateur (ou un responsable). */
    private function ensureCanWork(Request $request, PickList $list): void
    {
        $user = $this->user($request);

        abort_unless(
            $list->picker_id === null || $list->picker_id === $user->getAuthIdentifier() || $user->hasPermission('orders.manage'),
            403,
            'Cette préparation est prise en charge par un autre préparateur.',
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function guard(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (PickingNotAllowed|InsufficientStock|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['picking' => [$e->getMessage()]]);
        }
    }
}
