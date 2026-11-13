<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Delivery\Actions\ConfirmDeliveryAction;
use App\Modules\Delivery\Actions\FailDeliveryAction;
use App\Modules\Delivery\Actions\RecordLocationAction;
use App\Modules\Delivery\Actions\StartDeliveryAction;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Http\Requests\ConfirmDeliveryRequest;
use App\Modules\Delivery\Http\Requests\LocationRequest;
use App\Modules\Delivery\Http\Requests\ReasonRequest;
use App\Modules\Delivery\Http\Resources\DeliveryResource;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/** Application du livreur : sa tournée, le départ, la position, la remise avec code, l'échec. */
class CourierController
{
    public function __construct(private readonly OrderDeliveryPort $orders) {}

    #[OA\Get(
        path: '/api/v1/delivery/my-tour',
        summary: 'Ma tournée : livraisons affectées, en cours ou à retenter, par ordre de créneau',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        responses: [
            new OA\Response(response: 200, description: 'Tournée', content: new OA\JsonContent(required: ['data'], properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Delivery')),
            ])),
            new OA\Response(response: 403, description: 'Interdit'),
        ],
    )]
    public function tour(Request $request): AnonymousResourceCollection
    {
        $deliveries = Delivery::where('courier_id', $this->courierId($request))
            ->whereIn('status', ['assigned', 'out_for_delivery', 'failed'])
            ->get()
            ->sortBy(fn (Delivery $d) => DeliverySlot::find($this->orders->find($d->order_id)?->slotId)?->starts_at->timestamp ?? PHP_INT_MAX)
            ->values();

        return DeliveryResource::collection($deliveries);
    }

    #[OA\Post(
        path: '/api/v1/delivery/deliveries/{delivery}/start',
        summary: 'Partir en livraison (ou repartir après un échec)',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        parameters: [new OA\Parameter(name: 'delivery', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Livraison démarrée', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Delivery')])),
            new OA\Response(response: 404, description: 'Pas votre livraison'),
            new OA\Response(response: 422, description: 'État incompatible'),
        ],
    )]
    public function start(Request $request, Delivery $delivery, StartDeliveryAction $action): DeliveryResource
    {
        $courierId = $this->own($request, $delivery);

        return $this->guard(fn () => new DeliveryResource($action->execute($delivery, $courierId)));
    }

    #[OA\Post(
        path: '/api/v1/delivery/deliveries/{delivery}/location',
        summary: 'Envoyer ma position (uniquement pendant la livraison, dernière position conservée)',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        parameters: [new OA\Parameter(name: 'delivery', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['latitude', 'longitude'],
            properties: [new OA\Property(property: 'latitude', type: 'number'), new OA\Property(property: 'longitude', type: 'number')],
        )),
        responses: [new OA\Response(response: 204, description: 'Position enregistrée'), new OA\Response(response: 422, description: 'Livraison non en cours')],
    )]
    public function location(LocationRequest $request, Delivery $delivery, RecordLocationAction $action): JsonResponse
    {
        $courierId = $this->own($request, $delivery);
        $this->guard(fn () => $action->execute($delivery, $courierId, (float) $request->input('latitude'), (float) $request->input('longitude')));

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/v1/delivery/deliveries/{delivery}/confirm',
        summary: 'Confirmer la remise : code du client + espèces encaissées',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        parameters: [new OA\Parameter(name: 'delivery', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['code', 'cash_collected_minor'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: '482913'),
                new OA\Property(property: 'cash_collected_minor', type: 'integer', description: 'Espèces encaissées (0 si déjà payée)'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Commande remise', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Delivery')])),
            new OA\Response(response: 422, description: 'Code incorrect ou verrouillé, montant erroné, état incompatible'),
        ],
    )]
    public function confirm(ConfirmDeliveryRequest $request, Delivery $delivery, ConfirmDeliveryAction $action): DeliveryResource
    {
        $courierId = $this->own($request, $delivery);

        return $this->guard(fn () => new DeliveryResource($action->execute(
            $delivery, $courierId, $request->string('code')->toString(), $request->integer('cash_collected_minor'),
        )));
    }

    #[OA\Post(
        path: '/api/v1/delivery/deliveries/{delivery}/fail',
        summary: 'Déclarer un échec de livraison (client absent, adresse introuvable)',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        parameters: [new OA\Parameter(name: 'delivery', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['reason'],
            properties: [new OA\Property(property: 'reason', type: 'string')],
        )),
        responses: [new OA\Response(response: 200, description: 'Échec enregistré', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Delivery')]))],
    )]
    public function fail(ReasonRequest $request, Delivery $delivery, FailDeliveryAction $action): DeliveryResource
    {
        $courierId = $this->own($request, $delivery);

        return $this->guard(fn () => new DeliveryResource($action->execute($delivery, $courierId, $request->string('reason')->toString())));
    }

    private function courierId(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return (int) $user->getAuthIdentifier();
    }

    /** Un livreur ne voit et ne manipule que ses propres livraisons ; les autres lui sont introuvables. */
    private function own(Request $request, Delivery $delivery): int
    {
        $courierId = $this->courierId($request);
        abort_unless($delivery->courier_id === $courierId, 404);

        return $courierId;
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
