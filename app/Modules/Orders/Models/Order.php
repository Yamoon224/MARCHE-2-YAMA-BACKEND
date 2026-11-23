<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Database\Factories\OrderFactory;
use App\Modules\Orders\Enums\OrderStatus;
use App\Shared\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property OrderStatus $status
 * @property array<string, mixed>|null $delivery_address
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'number', 'user_id', 'status', 'currency', 'subtotal_minor', 'delivery_fee_minor',
        'discount_minor', 'total_minor', 'channel', 'idempotency_key',
        'fulfillment', 'delivery_zone_id', 'delivery_slot_id', 'delivery_address', 'contact_phone', 'final_total_minor', 'adjustment_minor',
    ];

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_minor' => 'integer',
            'delivery_fee_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'delivery_address' => 'array',
            'final_total_minor' => 'integer',
            'adjustment_minor' => 'integer',
        ];
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }
}
