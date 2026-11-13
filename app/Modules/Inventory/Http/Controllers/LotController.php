<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\RecallLotAction;
use App\Modules\Inventory\Http\Requests\RecallLotRequest;
use App\Modules\Inventory\Models\LotRecall;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Services\LotTraceability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'StockLot', required: ['id', 'product_id', 'lot_code', 'quantity_on_hand', 'available', 'is_blocked'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'product_id', type: 'integer'),
    new OA\Property(property: 'lot_code', type: 'string'),
    new OA\Property(property: 'expires_on', type: 'string', nullable: true, example: '2026-12-01'),
    new OA\Property(property: 'quantity_on_hand', type: 'integer', description: 'Pièces, grammes ou millilitres'),
    new OA\Property(property: 'quantity_reserved', type: 'integer'),
    new OA\Property(property: 'available', type: 'integer', description: 'Physique - réservé'),
    new OA\Property(property: 'is_blocked', type: 'boolean'),
    new OA\Property(property: 'recalled', type: 'boolean'),
])]
#[OA\Schema(schema: 'LotRecallOrder', required: ['order_id', 'quantity'], properties: [
    new OA\Property(property: 'order_id', type: 'integer'),
    new OA\Property(property: 'customer_id', type: 'integer', nullable: true),
    new OA\Property(property: 'quantity', type: 'integer'),
])]
#[OA\Schema(schema: 'LotRecallReport', required: ['lot', 'reason', 'delivered', 'reserved'], properties: [
    new OA\Property(property: 'lot', ref: '#/components/schemas/StockLot'),
    new OA\Property(property: 'reason', type: 'string'),
    new OA\Property(property: 'recalled_at', type: 'string', nullable: true),
    new OA\Property(property: 'delivered', type: 'array', description: 'Clients ayant reçu le lot : à prévenir', items: new OA\Items(ref: '#/components/schemas/LotRecallOrder')),
    new OA\Property(property: 'reserved', type: 'array', description: 'Commandes en attente qui réservent le lot : à réallouer', items: new OA\Items(ref: '#/components/schemas/LotRecallOrder')),
])]
class LotController
{
    #[OA\Get(
        path: '/api/v1/inventory/lots',
        summary: 'Lots en stock (filtrables : produit, bloqués, péremption proche)',
        security: [['sanctum' => []]],
        tags: ['Stock'],
        parameters: [
            new OA\Parameter(name: 'product_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'blocked', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'expiring_in_days', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0)),
        ],
        responses: [new OA\Response(response: 200, description: 'Lots', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/StockLot')),
        ]))],
    )]
    public function index(Request $request): JsonResponse
    {
        $days = $request->query('expiring_in_days');

        $lots = StockLot::query()
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('blocked'), fn ($q) => $q->where('is_blocked', $request->boolean('blocked')))
            ->when(is_numeric($days), fn ($q) => $q->whereNotNull('expires_on')->where('expires_on', '<=', now()->addDays((int) $days)->toDateString()))
            ->orderByRaw('expires_on IS NULL, expires_on ASC')->orderBy('id')
            ->limit(200)->get();

        $recalled = LotRecall::whereIn('stock_lot_id', $lots->pluck('id'))->pluck('stock_lot_id')->flip();

        return response()->json(['data' => $lots->map(fn (StockLot $l) => $this->present($l, $recalled->has($l->id)))->all()]);
    }

    #[OA\Post(
        path: '/api/v1/inventory/lots/{lot}/recall',
        summary: 'Rappeler un lot (alerte sanitaire) : blocage immédiat et traçabilité des commandes concernées',
        security: [['sanctum' => []]],
        tags: ['Stock'],
        parameters: [new OA\Parameter(name: 'lot', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['reason'],
            properties: [new OA\Property(property: 'reason', type: 'string', example: 'Listeria détectée chez le fournisseur')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Lot rappelé et rapport de traçabilité', content: new OA\JsonContent(ref: '#/components/schemas/LotRecallReport')),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Motif manquant'),
        ],
    )]
    public function recall(RecallLotRequest $request, StockLot $lot, RecallLotAction $action, LotTraceability $trace): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $recall = $action->execute($lot, $request->string('reason')->toString(), (int) $user->getAuthIdentifier());

        return $this->buildReport($lot->refresh(), $recall, $trace);
    }

    #[OA\Get(
        path: '/api/v1/inventory/lots/{lot}/recall',
        summary: 'Rapport de rappel : clients ayant reçu le lot et commandes en attente qui le réservent',
        security: [['sanctum' => []]],
        tags: ['Stock'],
        parameters: [new OA\Parameter(name: 'lot', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Rapport', content: new OA\JsonContent(ref: '#/components/schemas/LotRecallReport')),
            new OA\Response(response: 404, description: 'Ce lot n\'a pas été rappelé'),
        ],
    )]
    public function report(StockLot $lot, LotTraceability $trace): JsonResponse
    {
        $recall = LotRecall::where('stock_lot_id', $lot->id)->first();
        abort_if($recall === null, 404, "Ce lot n'a pas été rappelé.");

        return $this->buildReport($lot, $recall, $trace);
    }

    private function buildReport(StockLot $lot, LotRecall $recall, LotTraceability $trace): JsonResponse
    {
        return response()->json([
            'lot' => $this->present($lot, true),
            'reason' => $recall->reason,
            'recalled_at' => $recall->created_at?->toIso8601String(),
            ...$trace->forLot($lot),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(StockLot $lot, bool $recalled): array
    {
        return [
            'id' => $lot->id,
            'product_id' => $lot->product_id,
            'lot_code' => $lot->lot_code,
            'expires_on' => $lot->expires_on?->toDateString(),
            'quantity_on_hand' => $lot->quantity_on_hand,
            'quantity_reserved' => $lot->quantity_reserved,
            'available' => $lot->available(),
            'is_blocked' => $lot->is_blocked,
            'recalled' => $recalled,
        ];
    }
}
