<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon $recorded_at */
class DeliveryLocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['delivery_id', 'latitude', 'longitude', 'recorded_at'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'recorded_at' => 'datetime'];
    }
}
