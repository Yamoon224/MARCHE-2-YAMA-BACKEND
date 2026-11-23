<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Http\Controllers;

use App\Shared\PublicId;
use App\Modules\Identity\Contracts\PermissionHolder;
use App\Modules\Invoicing\Http\Resources\InvoiceResource;
use App\Modules\Invoicing\Models\Invoice;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class InvoiceController
{
    #[OA\Get(
        path: '/api/v1/orders/{orderId}/invoice',
        summary: 'Facture d\'une commande payée',
        security: [['sanctum' => []]],
        tags: ['Facturation'],
        parameters: [new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Facture', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Invoice')])),
            new OA\Response(response: 404, description: 'Aucune facture pour cette commande'),
        ],
    )]
    public function show(Request $request, string $orderId): InvoiceResource
    {
        $orderId = PublicId::resolveOrFail('orders', $orderId, 'Commande introuvable.'); // UUID public -> clé interne

        $user = $request->user();
        abort_unless($user instanceof PermissionHolder, 401);

        $invoice = Invoice::with(['lines', 'creditNotes'])->where('order_id', $orderId)->first();

        $allowed = $invoice !== null && (
            $invoice->user_id === $user->getAuthIdentifier()
            || $user->hasPermission('invoicing.view')
        );

        // 404 aussi pour une facture d'un autre client : pas d'énumération possible.
        abort_unless($allowed, 404, 'Facture introuvable.');

        return new InvoiceResource($invoice);
    }
}
