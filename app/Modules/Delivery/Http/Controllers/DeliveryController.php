<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Delivery\Http\Requests\StoreSlotRequest;
use App\Modules\Delivery\Http\Requests\StoreZoneRequest;
use App\Modules\Delivery\Http\Resources\SlotResource;
use App\Modules\Delivery\Http\Resources\ZoneResource;
use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Delivery\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class DeliveryController
{
    #[OA\Get(
        path: '/api/v1/delivery/zones',
        summary: 'Zones desservies',
        tags: ['Livraison'],
        responses: [new OA\Response(response: 200, description: 'Zones actives', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DeliveryZone')),
        ]))],
    )]
    public function zones(): AnonymousResourceCollection
    {
        return ZoneResource::collection(DeliveryZone::where('is_active', true)->orderBy('name')->get());
    }

    #[OA\Get(
        path: '/api/v1/delivery/zones/{zone}/slots',
        summary: 'Créneaux réservables d\'une zone (places restantes, délai de préparation respecté)',
        tags: ['Livraison'],
        parameters: [
            new OA\Parameter(name: 'zone', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'AAAA-MM-JJ ; sinon les 7 prochains jours', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Créneaux', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DeliverySlot')),
        ]))],
    )]
    public function slots(int $zone): AnonymousResourceCollection
    {
        $earliest = now()->addMinutes((int) config('delivery.min_lead_minutes'));
        $date = request()->string('date')->toString();

        $slots = DeliverySlot::query()
            ->where('delivery_zone_id', $zone)
            ->where('starts_at', '>=', $earliest)
            ->whereColumn('booked', '<', 'capacity')
            ->when($date !== '', fn ($q) => $q->whereDate('starts_at', $date), fn ($q) => $q->where('starts_at', '<', now()->addDays(7)))
            ->orderBy('starts_at')
            ->get();

        return SlotResource::collection($slots);
    }

    #[OA\Post(
        path: '/api/v1/delivery/zones',
        summary: 'Créer une zone de livraison',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'fee_minor'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'fee_minor', type: 'integer'),
                new OA\Property(property: 'free_above_minor', type: 'integer', nullable: true),
                new OA\Property(property: 'min_order_minor', type: 'integer'),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Zone créée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/DeliveryZone')])),
            new OA\Response(response: 403, description: 'Interdit'),
        ],
    )]
    public function storeZone(StoreZoneRequest $request): JsonResponse
    {
        return (new ZoneResource(DeliveryZone::create($request->validated())))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/api/v1/delivery/slots',
        summary: 'Ouvrir un créneau de livraison avec sa capacité',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['delivery_zone_id', 'starts_at', 'ends_at', 'capacity'],
            properties: [
                new OA\Property(property: 'delivery_zone_id', type: 'integer'),
                new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'ends_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'capacity', type: 'integer', minimum: 1),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Créneau créé', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/DeliverySlot')])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function storeSlot(StoreSlotRequest $request): JsonResponse
    {
        return (new SlotResource(DeliverySlot::create($request->validated())))->response()->setStatusCode(201);
    }
}
