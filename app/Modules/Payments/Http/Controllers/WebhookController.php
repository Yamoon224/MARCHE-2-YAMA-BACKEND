<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Payments\Actions\ProcessWebhookAction;
use App\Modules\Payments\Exceptions\InvalidWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class WebhookController
{
    #[OA\Post(
        path: '/api/v1/webhooks/payments/{provider}',
        summary: 'Webhook d\'un agrégateur de paiement (signé, idempotent)',
        tags: ['Paiements'],
        parameters: [
            new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'fake')),
            new OA\Parameter(name: 'X-Signature', in: 'header', required: false, description: 'Signature HMAC (simulateur) ; Stripe envoie Stripe-Signature', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Événement pris en compte (ou déjà traité)'),
            new OA\Response(response: 401, description: 'Signature invalide'),
            new OA\Response(response: 404, description: 'Fournisseur inconnu'),
            new OA\Response(response: 422, description: 'Événement mal formé'),
        ],
    )]
    public function handle(Request $request, ProcessWebhookAction $action, string $provider): JsonResponse
    {
        try {
            $outcome = $action->execute($provider, $request->getContent(), $request->header('X-Signature') ?? $request->header('Stripe-Signature'));
        } catch (InvalidWebhook $e) {
            $status = match (true) {
                str_contains($e->getMessage(), 'Signature') => 401,
                str_contains($e->getMessage(), 'inconnu') => 404,
                default => 422,
            };

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(['outcome' => $outcome->value]);
    }
}
