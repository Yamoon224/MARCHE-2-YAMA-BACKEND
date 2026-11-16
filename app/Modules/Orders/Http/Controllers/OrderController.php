<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Identity\Contracts\PermissionHolder;
use App\Modules\Orders\Actions\PlaceOrderAction;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Domain\OrderAccess;
use App\Modules\Orders\DTO\DeliveryChoice;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\DeliveryUnavailable;
use App\Modules\Orders\Exceptions\InsufficientStock;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Exceptions\ProductNotOrderable;
use App\Modules\Orders\Http\Requests\PlaceOrderRequest;
use App\Modules\Orders\Http\Requests\TransitionOrderRequest;
use App\Modules\Orders\Http\Resources\OrderResource;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class OrderController
{
    public function __construct(private readonly OrderAccess $access) {}

    #[OA\Post(
        path: '/api/v1/orders',
        summary: 'Passer une commande (prix calculés par le serveur)',
        security: [['sanctum' => []]],
        tags: ['Commandes'],
        parameters: [new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['items'],
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    required: ['product_id', 'quantity'],
                    properties: [
                        new OA\Property(property: 'product_id', type: 'integer'),
                        new OA\Property(property: 'quantity', type: 'integer', description: 'Unité de base : pièces, grammes ou millilitres'),
                        new OA\Property(property: 'substitution', type: 'string', enum: ['allow', 'refuse'], description: 'Consigne en cas de rupture'),
                    ],
                )),
                new OA\Property(property: 'fulfillment', type: 'string', enum: ['pickup', 'delivery'], description: 'Retrait en magasin par défaut'),
                new OA\Property(property: 'delivery', type: 'object', description: 'Obligatoire pour une livraison', required: ['zone_id', 'slot_id', 'phone', 'address'], properties: [
                    new OA\Property(property: 'zone_id', type: 'integer'),
                    new OA\Property(property: 'slot_id', type: 'integer'),
                    new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
                    new OA\Property(property: 'address', type: 'object', required: ['neighborhood'], properties: [
                        new OA\Property(property: 'neighborhood', type: 'string'),
                        new OA\Property(property: 'landmark', type: 'string', nullable: true),
                        new OA\Property(property: 'latitude', type: 'number', nullable: true),
                        new OA\Property(property: 'longitude', type: 'number', nullable: true),
                    ]),
                ]),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Commande créée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')])),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Validation ou produit indisponible'),
        ],
    )]
    public function store(PlaceOrderRequest $request, PlaceOrderAction $action): JsonResponse
    {
        $user = $this->user($request);

        try {
            /** @var list<array{product_id: int, quantity: int}> $items */
            $items = $request->validated('items');
            $choice = $request->input('fulfillment') === 'delivery'
                ? new DeliveryChoice(
                    (int) $request->input('delivery.zone_id'), (int) $request->input('delivery.slot_id'),
                    (array) $request->input('delivery.address'), (string) $request->input('delivery.phone'),
                )
                : null;

            $order = $action->execute((int) $user->getAuthIdentifier(), $items, $request->header('Idempotency-Key'), $choice);
        } catch (DeliveryUnavailable $e) {
            throw ValidationException::withMessages(['delivery' => [$e->getMessage()]]);
        } catch (ProductNotOrderable|InsufficientStock $e) {
            throw ValidationException::withMessages(['items' => [$e->getMessage()]]);
        }

        return (new OrderResource($order))->response()->setStatusCode($order->wasRecentlyCreated ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/v1/orders',
        summary: 'Mes commandes (toutes les commandes pour le personnel autorisé)',
        security: [['sanctum' => []]],
        tags: ['Commandes'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [new OA\Response(response: 200, description: 'Commandes paginées', content: new OA\JsonContent(ref: '#/components/schemas/OrderPage'))],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $seesAll = $user->hasPermission('orders.view') || $user->hasPermission('orders.manage');

        $orders = Order::query()
            ->when(! $seesAll, fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    #[OA\Get(
        path: '/api/v1/orders/{order}',
        summary: 'Détail d\'une commande avec lignes et historique',
        security: [['sanctum' => []]],
        tags: ['Commandes'],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Commande', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 404, description: 'Introuvable'),
        ],
    )]
    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($this->access->canView($this->user($request), $order), 403, 'Action non autorisée.');

        return new OrderResource($order->load(['lines', 'history']));
    }

    #[OA\Post(
        path: '/api/v1/orders/{order}/transitions',
        summary: 'Changer le statut d\'une commande (machine à états)',
        security: [['sanctum' => []]],
        tags: ['Commandes'],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['to'],
            properties: [
                new OA\Property(property: 'to', type: 'string', example: 'confirmed'),
                new OA\Property(property: 'reason', type: 'string', nullable: true, description: 'Obligatoire pour annulation, réclamation, échec de livraison et retour'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Commande mise à jour', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Transition interdite ou motif manquant'),
        ],
    )]
    public function transition(TransitionOrderRequest $request, Order $order, TransitionOrderAction $action): OrderResource
    {
        $user = $this->user($request);
        $to = OrderStatus::from($request->string('to')->toString());

        abort_unless($this->access->canTransition($user, $order, $to), 403, 'Action non autorisée.');

        // « Prête » ne s'obtient que par la clôture de la préparation (pesée, remplacements, stock ajusté).
        if ($to === OrderStatus::Ready && $order->status === OrderStatus::Preparing) {
            throw ValidationException::withMessages(['to' => ["Terminez la préparation (liste de picking) pour passer la commande à l'état prête."]]);
        }

        // La livraison à domicile avance uniquement par le module Delivery : code de confirmation, encaissement
        // et position du livreur ne se contournent pas par l'API générale. Le retrait en magasin reste ici.
        $deliveryStages = [OrderStatus::Assigned, OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::DeliveryFailed, OrderStatus::Returned];
        if ($order->fulfillment === 'delivery' && in_array($to, $deliveryStages, true)) {
            throw ValidationException::withMessages(['to' => ['Les étapes de livraison à domicile se gèrent depuis la tournée du livreur (code de confirmation et encaissement).']]);
        }

        try {
            $updated = $action->execute($order, $to, (int) $user->getAuthIdentifier(), $request->input('reason'));
        } catch (InvalidOrderTransition $e) {
            throw ValidationException::withMessages(['to' => [$e->getMessage()]]);
        }

        return new OrderResource($updated->load(['lines', 'history']));
    }

    private function user(Request $request): PermissionHolder
    {
        $user = $request->user();
        abort_unless($user instanceof PermissionHolder, 401);

        return $user;
    }
}
