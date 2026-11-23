<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Shared\PublicId;
use App\Modules\Identity\Contracts\PermissionHolder;
use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Payments\Actions\RefundPaymentAction;
use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Exceptions\RefundNotAllowed;
use App\Modules\Payments\Http\Requests\StoreRefundRequest;
use App\Modules\Payments\Http\Resources\RefundResource;
use App\Modules\Payments\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class RefundController
{
    public function __construct(private readonly OrderPaymentPort $orders, private readonly RefundPaymentAction $refunds) {}

    #[OA\Get(
        path: '/api/v1/orders/{orderId}/refunds',
        summary: 'Remboursements d\'une commande',
        security: [['sanctum' => []]],
        tags: ['Paiements'],
        parameters: [new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Remboursements et montant encore remboursable', content: new OA\JsonContent(
                required: ['data', 'meta'],
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Refund')),
                    new OA\Property(property: 'meta', type: 'object', required: ['refundable_minor'], properties: [
                        new OA\Property(property: 'refundable_minor', type: 'integer', description: 'Encaissé - déjà remboursé (ou en cours)'),
                    ]),
                ],
            )),
            new OA\Response(response: 404, description: 'Commande introuvable'),
        ],
    )]
    public function index(Request $request, string $orderId): AnonymousResourceCollection
    {
        $orderId = PublicId::resolveOrFail('orders', $orderId, 'Commande introuvable.'); // UUID public -> clé interne

        $user = $this->user($request);
        $order = $this->orders->findPayable($orderId);

        // 404 aussi pour la commande d'un autre client : pas d'énumération possible.
        abort_unless($order !== null && ($order->userId === $user->getAuthIdentifier() || $user->hasPermission('orders.view')), 404, 'Commande introuvable.');

        return RefundResource::collection(Refund::where('order_id', $orderId)->orderBy('id')->get())
            ->additional(['meta' => ['refundable_minor' => $this->refunds->refundable($orderId)]]);
    }

    #[OA\Post(
        path: '/api/v1/orders/{orderId}/refunds',
        summary: 'Rembourser un montant au client (geste commercial, réclamation)',
        security: [['sanctum' => []]],
        tags: ['Paiements'],
        parameters: [
            new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['amount_minor', 'reason'],
            properties: [
                new OA\Property(property: 'amount_minor', type: 'integer'),
                new OA\Property(property: 'reason', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Remboursements créés', content: new OA\JsonContent(required: ['data'], properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Refund')),
            ])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 404, description: 'Commande introuvable'),
            new OA\Response(response: 422, description: 'Montant supérieur au remboursable'),
        ],
    )]
    public function store(StoreRefundRequest $request, string $orderId, RefundPaymentAction $action): JsonResponse
    {
        $orderId = PublicId::resolveOrFail('orders', $orderId, 'Commande introuvable.'); // UUID public -> clé interne

        abort_if($this->orders->findPayable($orderId) === null, 404, 'Commande introuvable.');

        try {
            $refunds = $action->execute(
                $orderId, $request->integer('amount_minor'), RefundKind::Manual, $request->string('reason')->toString(),
                // Sans clé fournie, chaque appel est un nouveau geste ; avec clé, un rejeu ne rembourse pas deux fois.
                'manual:'.$orderId.':'.($request->header('Idempotency-Key') ?: (string) Str::uuid()),
            );
        } catch (RefundNotAllowed $e) {
            throw ValidationException::withMessages(['amount_minor' => [$e->getMessage()]]);
        }

        return RefundResource::collection(collect($refunds))->response()->setStatusCode(201);
    }

    private function user(Request $request): PermissionHolder
    {
        $user = $request->user();
        abort_unless($user instanceof PermissionHolder, 401);

        return $user;
    }
}
