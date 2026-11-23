<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Shared\PublicId;
use App\Modules\Payments\Actions\InitiatePaymentAction;
use App\Modules\Payments\Actions\ValidateCashOnDeliveryAction;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Exceptions\GatewayFailure;
use App\Modules\Payments\Exceptions\PaymentNotAllowed;
use App\Modules\Payments\Http\Requests\InitiatePaymentRequest;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PaymentController
{
    #[OA\Post(
        path: '/api/v1/orders/{orderId}/payments',
        summary: 'Démarrer le paiement d\'une commande (montant fixé par le serveur)',
        security: [['sanctum' => []]],
        tags: ['Paiements'],
        parameters: [
            new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['method'],
            properties: [new OA\Property(property: 'method', type: 'string', enum: ['mobile_money', 'card', 'cash_on_delivery'])],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Paiement créé', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Payment')])),
            new OA\Response(response: 404, description: 'Commande introuvable'),
            new OA\Response(response: 422, description: 'Commande non payable'),
            new OA\Response(response: 503, description: 'Paiement par carte indisponible (agrégateur non configuré ou injoignable)'),
        ],
    )]
    public function store(InitiatePaymentRequest $request, string $orderId, InitiatePaymentAction $action): JsonResponse
    {
        $orderId = PublicId::resolveOrFail('orders', $orderId, 'Commande introuvable.'); // UUID public -> clé interne

        $user = $request->user();
        abort_if($user === null, 401);

        try {
            $payment = $action->execute(
                (int) $user->getAuthIdentifier(),
                $orderId,
                PaymentMethod::from($request->string('method')->toString()),
                $request->header('Idempotency-Key'),
            );
        } catch (PaymentNotAllowed $e) {
            abort_if($e->getMessage() === PaymentNotAllowed::orderNotFound()->getMessage(), 404, $e->getMessage());
            throw ValidationException::withMessages(['order' => [$e->getMessage()]]);
        } catch (GatewayFailure $e) {
            // Agrégateur non configuré ou injoignable : la commande reste en attente, le client pourra réessayer.
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return (new PaymentResource($payment))->response()->setStatusCode($payment->wasRecentlyCreated ? 201 : 200);
    }

    #[OA\Post(
        path: '/api/v1/payments/{payment}/validate-cash-on-delivery',
        summary: 'Valider une commande à payer à la livraison (opérateur)',
        security: [['sanctum' => []]],
        tags: ['Paiements'],
        parameters: [new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Commande validée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Payment')])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Paiement non éligible'),
        ],
    )]
    public function validateCashOnDelivery(Request $request, Payment $payment, ValidateCashOnDeliveryAction $action): PaymentResource
    {
        try {
            return new PaymentResource($action->execute($payment));
        } catch (PaymentNotAllowed $e) {
            throw ValidationException::withMessages(['payment' => [$e->getMessage()]]);
        }
    }
}
