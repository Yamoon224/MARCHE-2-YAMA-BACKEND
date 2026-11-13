<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use App\Modules\Delivery\Database\Factories\DeliverySlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
class DeliverySlot extends Model
{
    /** @use HasFactory<DeliverySlotFactory> */
    use HasFactory;

    protected $fillable = ['delivery_zone_id', 'starts_at', 'ends_at', 'capacity', 'booked'];

    protected static function newFactory(): DeliverySlotFactory
    {
        return DeliverySlotFactory::new();
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'booked' => 'integer',
        ];
    }

    public function remaining(): int
    {
        return max(0, $this->capacity - $this->booked);
    }
}
