<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use App\Modules\Delivery\Database\Factories\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    /** @use HasFactory<DeliveryZoneFactory> */
    use HasFactory;

    protected $fillable = ['name', 'fee_minor', 'free_above_minor', 'min_order_minor', 'is_active'];

    protected static function newFactory(): DeliveryZoneFactory
    {
        return DeliveryZoneFactory::new();
    }

    protected function casts(): array
    {
        return [
            'fee_minor' => 'integer',
            'free_above_minor' => 'integer',
            'min_order_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
