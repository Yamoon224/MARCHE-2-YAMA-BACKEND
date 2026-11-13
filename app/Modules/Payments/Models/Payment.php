<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Modules\Payments\Database\Factories\PaymentFactory;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property PaymentStatus $status
 * @property PaymentMethod $method
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'reference', 'order_id', 'provider', 'provider_reference', 'method', 'status',
        'amount_minor', 'currency', 'idempotency_key', 'needs_refund', 'note',
    ];

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'amount_minor' => 'integer',
            'needs_refund' => 'boolean',
        ];
    }
}
