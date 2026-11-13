<?php

declare(strict_types=1);

namespace App\Modules\Pos\Http\Controllers;

use App\Modules\Orders\Exceptions\InsufficientStock;
use App\Modules\Orders\Exceptions\ProductNotOrderable;
use App\Modules\Payments\Exceptions\PaymentNotAllowed;
use App\Modules\Pos\Actions\CloseSessionAction;
use App\Modules\Pos\Actions\OpenSessionAction;
use App\Modules\Pos\Actions\RecordSaleAction;
use App\Modules\Pos\Exceptions\PosNotAllowed;
use App\Modules\Pos\Http\Requests\CloseSessionRequest;
use App\Modules\Pos\Http\Requests\OpenSessionRequest;
use App\Modules\Pos\Http\Requests\RecordSaleRequest;
use App\Modules\Pos\Models\PosSale;
use App\Modules\Pos\Models\PosSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PosSession', required: ['id', 'cashier_id', 'opening_float_minor', 'sales_count', 'cash_sales_minor', 'other_sales_minor', 'expected_cash_minor', 'is_open'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'cashier_id', type: 'integer'),
    new OA\Property(property: 'opening_float_minor', type: 'integer'),
    new OA\Property(property: 'sales_count', type: 'integer'),
    new OA\Property(property: 'cash_sales_minor', type: 'integer'),
    new OA\Property(property: 'other_sales_minor', type: 'integer', description: 'Mobile money et carte (hors tiroir)'),
    new OA\Property(property: 'expected_cash_minor', type: 'integer', description: 'Fonds de caisse + ventes en espèces'),
    new OA\Property(property: 'counted_minor', type: 'integer', nullable: true),
    new OA\Property(property: 'variance_minor', type: 'integer', nullable: true, description: 'Compté - attendu : négatif = manquant'),
    new OA\Property(property: 'note', type: 'string', nullable: true),
    new OA\Property(property: 'is_open', type: 'boolean'),
    new OA\Property(property: 'opened_at', type: 'string'),
    new OA\Property(property: 'closed_at', type: 'string', nullable: true),
])]
#[OA\Schema(schema: 'PosReceiptLine', required: ['name', 'quantity', 'sale_unit', 'unit_price_minor', 'line_total_minor'], properties: [
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'quantity', type: 'integer'),
    new OA\Property(property: 'sale_unit', type: 'string'),
    new OA\Property(property: 'unit_price_minor', type: 'integer'),
    new OA\Property(property: 'line_total_minor', type: 'integer'),
])]
#[OA\Schema(schema: 'PosSale', required: ['id', 'order_id', 'order_number', 'tender', 'total_minor', 'tendered_minor', 'change_minor', 'lines', 'created_at'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'order_id', type: 'integer'),
    new OA\Property(property: 'order_number', type: 'string'),
    new OA\Property(property: 'tender', type: 'string', enum: ['cash', 'mobile_money', 'card']),
    new OA\Property(property: 'total_minor', type: 'integer'),
    new OA\Property(property: 'tendered_minor', type: 'integer'),
    new OA\Property(property: 'change_minor', type: 'integer'),
    new OA\Property(property: 'lines', type: 'array', items: new OA\Items(ref: '#/components/schemas/PosReceiptLine')),
    new OA\Property(property: 'created_at', type: 'string'),
])]
class PosController
{
    #[OA\Get(
        path: '/api/v1/pos/sessions/current',
        summary: 'Ma caisse ouverte (ou rien si elle est fermée)',
        security: [['sanctum' => []]],
        tags: ['Caisse'],
        responses: [new OA\Response(response: 200, description: 'Caisse ouverte ou null', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/PosSession', nullable: true),
        ]))],
    )]
    public function current(Request $request): JsonResponse
    {
        $session = PosSession::where('cashier_id', $this->userId($request))->whereNull('closed_at')->first();

        return response()->json(['data' => $session === null ? null : $this->presentSession($session)]);
    }

    #[OA\Post(
        path: '/api/v1/pos/sessions',
        summary: "Ouvrir ma caisse avec son fonds de caisse",
        security: [['sanctum' => []]],
        tags: ['Caisse'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['opening_float_minor'], properties: [
            new OA\Property(property: 'opening_float_minor', type: 'integer', description: 'Monnaie de départ dans le tiroir, en F CFA'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Caisse ouverte', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PosSession')])),
            new OA\Response(response: 422, description: 'Caisse déjà ouverte'),
        ],
    )]
    public function open(OpenSessionRequest $request, OpenSessionAction $action): JsonResponse
    {
        $session = $this->guard(fn () => $action->execute($this->userId($request), $request->integer('opening_float_minor')));

        return response()->json(['data' => $this->presentSession($session)], 201);
    }

    #[OA\Post(
        path: '/api/v1/pos/sessions/{session}/close',
        summary: 'Fermer ma caisse après comptage du tiroir',
        security: [['sanctum' => []]],
        tags: ['Caisse'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['counted_minor'], properties: [
            new OA\Property(property: 'counted_minor', type: 'integer'),
            new OA\Property(property: 'note', type: 'string', nullable: true, description: "Obligatoire s'il y a un écart"),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Caisse fermée', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PosSession')])),
            new OA\Response(response: 422, description: 'Déjà fermée, ou écart non expliqué'),
        ],
    )]
    public function close(CloseSessionRequest $request, int $session, CloseSessionAction $action): JsonResponse
    {
        $closed = $this->guard(fn () => $action->execute($session, $this->userId($request), $request->integer('counted_minor'), $request->input('note')));

        return response()->json(['data' => $this->presentSession($closed)]);
    }

    #[OA\Get(
        path: '/api/v1/pos/sessions',
        summary: 'Historique des caisses (responsables)',
        security: [['sanctum' => []]],
        tags: ['Caisse'],
        responses: [new OA\Response(response: 200, description: 'Caisses, les plus récentes en premier', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PosSession')),
        ]))],
    )]
    public function index(): JsonResponse
    {
        return response()->json(['data' => PosSession::orderByDesc('id')->limit(100)->get()->map(fn (PosSession $s) => $this->presentSession($s))->values()]);
    }

    #[OA\Post(
        path: '/api/v1/pos/sales',
        summary: 'Encaisser une vente au comptoir (commande, paiement et remise en une opération)',
        security: [['sanctum' => []]],
        tags: ['Caisse'],
        parameters: [new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, schema: new OA\Schema(type: 'string', maxLength: 64))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['items', 'tender'], properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(required: ['product_id', 'quantity'], properties: [
                new OA\Property(property: 'product_id', type: 'integer'),
                new OA\Property(property: 'quantity', type: 'integer', description: 'Pièces, grammes ou millilitres'),
            ])),
            new OA\Property(property: 'tender', type: 'string', enum: ['cash', 'mobile_money', 'card']),
            new OA\Property(property: 'tendered_minor', type: 'integer', nullable: true, description: 'Espèces remises par le client (pour la monnaie)'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Vente enregistrée', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PosSale')])),
            new OA\Response(response: 200, description: 'Vente déjà enregistrée (rejeu de la même clé)'),
            new OA\Response(response: 422, description: 'Caisse fermée, stock insuffisant, montant reçu insuffisant'),
        ],
    )]
    public function sell(RecordSaleRequest $request, RecordSaleAction $action): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        $sale = $this->guard(fn () => $action->execute(
            $this->userId($request), $request->input('items'), $request->string('tender')->toString(),
            $request->filled('tendered_minor') ? $request->integer('tendered_minor') : null,
            is_string($key) && $key !== '' ? substr($key, 0, 64) : null,
        ));

        return response()->json(['data' => $this->presentSale($sale)], $sale->wasRecentlyCreated ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/v1/pos/sales/{sale}',
        summary: 'Ticket de caisse (réimpression)',
        security: [['sanctum' => []]],
        tags: ['Caisse'],
        parameters: [new OA\Parameter(name: 'sale', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Vente', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PosSale')])),
            new OA\Response(response: 404, description: "Introuvable (ou vente d'un autre caissier)"),
        ],
    )]
    public function receipt(Request $request, int $sale): JsonResponse
    {
        $found = PosSale::findOrFail($sale);
        $user = $request->user();
        abort_if($user === null, 401);

        $own = PosSession::whereKey($found->pos_session_id)->value('cashier_id') === (int) $user->getAuthIdentifier();
        abort_unless($own || ($user instanceof \App\Modules\Identity\Contracts\PermissionHolder && $user->hasPermission('pos.manage')), 404);

        return response()->json(['data' => $this->presentSale($found)]);
    }

    private function userId(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return (int) $user->getAuthIdentifier();
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
        } catch (PosNotAllowed|PaymentNotAllowed $e) {
            throw ValidationException::withMessages(['pos' => [$e->getMessage()]]);
        } catch (ProductNotOrderable|InsufficientStock $e) {
            throw ValidationException::withMessages(['items' => [$e->getMessage()]]);
        }
    }

    /** @return array<string, mixed> */
    private function presentSession(PosSession $session): array
    {
        $sales = PosSale::where('pos_session_id', $session->id);
        $cash = (int) (clone $sales)->where('tender', 'cash')->sum('total_minor');

        return [
            'id' => $session->id, 'cashier_id' => $session->cashier_id, 'opening_float_minor' => $session->opening_float_minor,
            'sales_count' => (clone $sales)->count(), 'cash_sales_minor' => $cash,
            'other_sales_minor' => (int) (clone $sales)->where('tender', '!=', 'cash')->sum('total_minor'),
            'expected_cash_minor' => $session->expected_cash_minor ?? $session->opening_float_minor + $cash,
            'counted_minor' => $session->counted_minor, 'variance_minor' => $session->variance_minor, 'note' => $session->note,
            'is_open' => $session->isOpen(), 'opened_at' => $session->opened_at->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentSale(PosSale $sale): array
    {
        return [
            'id' => $sale->id, 'order_id' => $sale->order_id, 'order_number' => $sale->order_number, 'tender' => $sale->tender,
            'total_minor' => $sale->total_minor, 'tendered_minor' => $sale->tendered_minor, 'change_minor' => $sale->change_minor,
            'lines' => $sale->lines, 'created_at' => $sale->created_at->toIso8601String(),
        ];
    }
}
