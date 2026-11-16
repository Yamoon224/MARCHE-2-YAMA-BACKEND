<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Resources;

use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Payment', required: ['id', 'reference', 'order_id', 'method', 'status', 'amount_minor', 'currency'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'reference', type: 'string'),
    new OA\Property(property: 'order_id', type: 'integer'),
    new OA\Property(property: 'method', type: 'string', enum: ['mobile_money', 'card', 'cash_on_delivery']),
    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'pending_on_delivery', 'succeeded', 'failed', 'flagged']),
    new OA\Property(property: 'amount_minor', type: 'integer'),
    new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
    new OA\Property(property: 'action_url', type: 'string', nullable: true, description: 'Page de paiement hébergée où rediriger le client (paiement par carte en attente)'),
])]
/** @property Payment $resource */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payment = $this->resource;

        return [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'order_id' => $payment->order_id,
            'method' => $payment->method->value,
            'status' => $payment->status->value,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            // Seulement tant que le paiement attend le client : une fois réglé ou échoué, l'adresse n'a plus d'intérêt.
            'action_url' => $payment->status === PaymentStatus::Pending ? $payment->action_url : null,
        ];
    }
}
