<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Identity\Contracts\CourierDirectory;
use App\Modules\Identity\Contracts\PermissionHolder;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TrackingController
{
    public function __construct(
        private readonly OrderDeliveryPort $orders,
        private readonly CourierDirectory $couriers,
    ) {}

    #[OA\Get(
        path: '/api/v1/orders/{orderId}/tracking',
        summary: 'Suivi de livraison : statut, livreur, position en direct et code de confirmation (client uniquement)',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        parameters: [new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Suivi', content: new OA\JsonContent(required: ['data'], properties: [
                new OA\Property(property: 'data', type: 'object', required: ['status'], properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'assigned', 'out_for_delivery', 'delivered', 'failed', 'returned']),
                    new OA\Property(property: 'courier_name', type: 'string', nullable: true),
                    new OA\Property(property: 'confirmation_code', type: 'string', nullable: true, description: 'Visible uniquement par le client, tant que la livraison est en cours'),
                    new OA\Property(property: 'location', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'latitude', type: 'number'),
                        new OA\Property(property: 'longitude', type: 'number'),
                        new OA\Property(property: 'recorded_at', type: 'string'),
                    ]),
                ]),
            ])),
            new OA\Response(response: 404, description: 'Aucun suivi pour cette commande'),
        ],
    )]
    public function show(Request $request, int $orderId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof PermissionHolder, 401);

        $order = $this->orders->find($orderId);
        $isOwner = $order !== null && $order->userId === $user->getAuthIdentifier();

        // 404 aussi pour la commande d'un autre client : pas d'énumération possible.
        abort_unless($order !== null && ($isOwner || $user->hasPermission('orders.view')), 404, 'Suivi introuvable.');

        $delivery = Delivery::with('location')->where('order_id', $orderId)->first();
        abort_if($delivery === null, 404, 'Aucune livraison à domicile pour cette commande.');

        $live = $delivery->status === DeliveryStatus::OutForDelivery && $delivery->location !== null;

        return response()->json(['data' => [
            'status' => $delivery->status->value,
            'courier_name' => $delivery->courier_id !== null ? $this->couriers->nameOf($delivery->courier_id) : null,
            // Le code n'est montré qu'au client, et seulement tant que la livraison n'est pas terminée.
            'confirmation_code' => $isOwner && $delivery->status->isActive() || ($isOwner && $delivery->status === DeliveryStatus::Pending)
                ? $delivery->confirmation_code
                : null,
            // La position n'est exposée que pendant la livraison.
            'location' => $live ? [
                'latitude' => $delivery->location->latitude,
                'longitude' => $delivery->location->longitude,
                'recorded_at' => $delivery->location->recorded_at->toIso8601String(),
            ] : null,
        ]]);
    }
}
