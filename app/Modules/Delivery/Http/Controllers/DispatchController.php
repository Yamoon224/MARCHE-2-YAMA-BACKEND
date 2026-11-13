<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Delivery\Actions\AssignCourierAction;
use App\Modules\Delivery\Actions\ReturnToStoreAction;
use App\Modules\Delivery\Actions\UnlockDeliveryCodeAction;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Http\Requests\AssignCourierRequest;
use App\Modules\Delivery\Http\Requests\ReasonRequest;
use App\Modules\Delivery\Http\Resources\DeliveryResource;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Identity\Contracts\CourierDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/** Régulation : le personnel confie les livraisons prêtes aux livreurs et traite les échecs. */
class DispatchController
{
    #[OA\Get(
        path: '/api/v1/delivery/deliveries',
        summary: 'Livraisons à traiter (prêtes, affectées, en cours, échouées par défaut)',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        parameters: [new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'assigned', 'out_for_delivery', 'delivered', 'failed', 'returned']))],
        responses: [
            new OA\Response(response: 200, description: 'Livraisons', content: new OA\JsonContent(required: ['data'], properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Delivery')),
            ])),
            new OA\Response(response: 403, description: 'Interdit'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->string('status')->toString();

        $deliveries = Delivery::query()
            ->when($status !== '', fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', ['pending', 'assigned', 'out_for_delivery', 'failed']))
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        return DeliveryResource::collection($deliveries);
    }

    #[OA\Get(
        path: '/api/v1/delivery/couriers',
        summary: 'Livreurs disponibles pour une affectation',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        responses: [new OA\Response(response: 200, description: 'Livreurs', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(required: ['id', 'name'], properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
            ])),
        ]))],
    )]
    public function couriers(CourierDirectory $couriers): JsonResponse
    {
        return response()->json(['data' => $couriers->all()]);
    }

    #[OA\Post(
        path: '/api/v1/delivery/deliveries/{delivery}/assign',
        summary: 'Confier la livraison à un livreur (ou la réaffecter avant son départ)',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        parameters: [new OA\Parameter(name: 'delivery', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['courier_id'],
            properties: [new OA\Property(property: 'courier_id', type: 'integer')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Livraison affectée', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Delivery')])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Pas un livreur, ou livraison déjà partie'),
        ],
    )]
    public function assign(AssignCourierRequest $request, Delivery $delivery, AssignCourierAction $action): DeliveryResource
    {
        $manager = $request->user();
        abort_if($manager === null, 401);

        return $this->guard(fn () => new DeliveryResource($action->execute($delivery, $request->integer('courier_id'), (int) $manager->getAuthIdentifier())));
    }

    #[OA\Post(
        path: '/api/v1/delivery/deliveries/{delivery}/return',
        summary: 'Retourner au magasin une livraison échouée (stock rendu, client remboursé)',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        parameters: [new OA\Parameter(name: 'delivery', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['reason'],
            properties: [new OA\Property(property: 'reason', type: 'string')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Livraison retournée', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Delivery')])),
            new OA\Response(response: 422, description: 'La livraison n\'est pas en échec'),
        ],
    )]
    public function returnToStore(ReasonRequest $request, Delivery $delivery, ReturnToStoreAction $action): DeliveryResource
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return $this->guard(fn () => new DeliveryResource($action->execute($delivery, (int) $user->getAuthIdentifier(), $request->string('reason')->toString())));
    }

    #[OA\Post(
        path: '/api/v1/delivery/deliveries/{delivery}/unlock',
        summary: 'Débloquer la saisie du code de confirmation (après vérification du client)',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        parameters: [new OA\Parameter(name: 'delivery', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Code débloqué', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Delivery')]))],
    )]
    public function unlock(Delivery $delivery, UnlockDeliveryCodeAction $action): DeliveryResource
    {
        return new DeliveryResource($action->execute($delivery));
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
        } catch (DeliveryNotAllowed $e) {
            throw ValidationException::withMessages(['delivery' => [$e->getMessage()]]);
        }
    }
}
