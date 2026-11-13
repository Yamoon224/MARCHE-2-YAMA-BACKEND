<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property RefundStatus $status
 * @property RefundKind $kind
 */
class Refund extends Model
{
    protected $fillable = [
        'reference', 'payment_id', 'order_id', 'amount_minor', 'currency', 'status', 'kind', 'reason',
        'source_key', 'provider_reference', 'failure',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'kind' => RefundKind::class,
            'amount_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
