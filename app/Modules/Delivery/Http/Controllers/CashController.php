<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Delivery\Actions\RecordCashHandoverAction;
use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Http\Requests\CashHandoverRequest;
use App\Modules\Delivery\Models\CashHandover;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Identity\Contracts\CourierDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/** Remise de fonds : ce que chaque livreur détient en espèces, et le comptage à son retour au magasin. */
#[OA\Schema(schema: 'CourierCash', required: ['courier_id', 'held_minor', 'deliveries_count'], properties: [
    new OA\Property(property: 'courier_id', type: 'integer'),
    new OA\Property(property: 'courier_name', type: 'string', nullable: true),
    new OA\Property(property: 'held_minor', type: 'integer'),
    new OA\Property(property: 'deliveries_count', type: 'integer'),
])]
#[OA\Schema(schema: 'CashHandover', required: ['id', 'courier_id', 'expected_minor', 'counted_minor', 'variance_minor', 'deliveries_count'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'courier_id', type: 'integer'),
    new OA\Property(property: 'received_by', type: 'integer'),
    new OA\Property(property: 'expected_minor', type: 'integer'),
    new OA\Property(property: 'counted_minor', type: 'integer'),
    new OA\Property(property: 'variance_minor', type: 'integer', description: 'Compté - attendu : négatif = manquant'),
    new OA\Property(property: 'deliveries_count', type: 'integer'),
    new OA\Property(property: 'note', type: 'string', nullable: true),
    new OA\Property(property: 'created_at', type: 'string', nullable: true),
])]
class CashController
{
    #[OA\Get(
        path: '/api/v1/delivery/cash',
        summary: 'Espèces détenues par chaque livreur (encaissées, non remises)',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        responses: [new OA\Response(response: 200, description: 'Soldes par livreur', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CourierCash')),
        ]))],
    )]
    public function index(CourierDirectory $couriers): JsonResponse
    {
        $rows = Delivery::query()
            ->where('status', DeliveryStatus::Delivered)
            ->whereNotNull('cash_collected_minor')
            ->whereNull('cash_handover_id')
            ->selectRaw('courier_id, SUM(cash_collected_minor) as held_minor, COUNT(*) as deliveries_count')
            ->groupBy('courier_id')
            ->orderByDesc('held_minor')
            ->toBase()
            ->get();

        return response()->json(['data' => $rows->map(fn ($row) => [
            'courier_id' => (int) $row->courier_id,
            'courier_name' => $couriers->nameOf((int) $row->courier_id),
            'held_minor' => (int) $row->held_minor,
            'deliveries_count' => (int) $row->deliveries_count,
        ])->values()]);
    }

    #[OA\Post(
        path: '/api/v1/delivery/cash/handovers',
        summary: "Enregistrer la remise de fonds d'un livreur (solde tout ce qu'il détient)",
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['courier_id', 'counted_minor'],
            properties: [
                new OA\Property(property: 'courier_id', type: 'integer'),
                new OA\Property(property: 'counted_minor', type: 'integer', description: 'Montant compté en F CFA'),
                new OA\Property(property: 'note', type: 'string', nullable: true, description: "Obligatoire s'il y a un écart"),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Remise enregistrée', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/CashHandover')])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Rien à remettre, pas un livreur, ou écart non expliqué'),
        ],
    )]
    public function store(CashHandoverRequest $request, RecordCashHandoverAction $action): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        try {
            $handover = $action->execute($request->integer('courier_id'), (int) $user->getAuthIdentifier(), $request->integer('counted_minor'), $request->input('note'));
        } catch (DeliveryNotAllowed $e) {
            throw ValidationException::withMessages(['cash' => [$e->getMessage()]]);
        }

        return response()->json(['data' => $this->present($handover)], 201);
    }

    #[OA\Get(
        path: '/api/v1/delivery/cash/handovers',
        summary: 'Historique des remises de fonds',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        responses: [new OA\Response(response: 200, description: 'Remises (les plus récentes en premier)', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CashHandover')),
        ]))],
    )]
    public function handovers(): JsonResponse
    {
        return response()->json(['data' => CashHandover::orderByDesc('id')->limit(100)->get()->map(fn (CashHandover $h) => $this->present($h))->values()]);
    }

    #[OA\Get(
        path: '/api/v1/delivery/my-cash',
        summary: 'Espèces que le livreur connecté détient encore',
        security: [['sanctum' => []]],
        tags: ['Livraison'],
        responses: [new OA\Response(response: 200, description: 'Solde du livreur', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', required: ['held_minor', 'deliveries_count'], properties: [
                new OA\Property(property: 'held_minor', type: 'integer'),
                new OA\Property(property: 'deliveries_count', type: 'integer'),
            ], type: 'object'),
        ]))],
    )]
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $query = Delivery::where('courier_id', (int) $user->getAuthIdentifier())
            ->where('status', DeliveryStatus::Delivered)->whereNotNull('cash_collected_minor')->whereNull('cash_handover_id');

        return response()->json(['data' => ['held_minor' => (int) $query->sum('cash_collected_minor'), 'deliveries_count' => $query->count()]]);
    }

    /** @return array<string, mixed> */
    private function present(CashHandover $handover): array
    {
        return [
            'id' => $handover->id, 'courier_id' => $handover->courier_id, 'received_by' => $handover->received_by,
            'expected_minor' => $handover->expected_minor, 'counted_minor' => $handover->counted_minor,
            'variance_minor' => $handover->variance_minor, 'deliveries_count' => $handover->deliveries_count,
            'note' => $handover->note, 'created_at' => $handover->created_at->toIso8601String(),
        ];
    }
}
