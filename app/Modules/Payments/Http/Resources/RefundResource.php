<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Resources;

use App\Modules\Payments\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Refund', required: ['id', 'reference', 'order_id', 'amount_minor', 'currency', 'status', 'kind', 'reason'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'reference', type: 'string'),
    new OA\Property(property: 'order_id', type: 'integer'),
    new OA\Property(property: 'amount_minor', type: 'integer'),
    new OA\Property(property: 'currency', type: 'string', example: 'XOF'),
    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'succeeded', 'failed']),
    new OA\Property(property: 'kind', type: 'string', enum: ['adjustment', 'cancellation', 'manual', 'claim', 'late_payment']),
    new OA\Property(property: 'reason', type: 'string'),
])]
/** @property Refund $resource */
class RefundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $refund = $this->resource;

        return [
            'id' => $refund->id,
            'reference' => $refund->reference,
            'order_id' => $refund->order_id,
            'amount_minor' => $refund->amount_minor,
            'currency' => $refund->currency,
            'status' => $refund->status->value,
            'kind' => $refund->kind->value,
            'reason' => $refund->reason,
        ];
    }
}
