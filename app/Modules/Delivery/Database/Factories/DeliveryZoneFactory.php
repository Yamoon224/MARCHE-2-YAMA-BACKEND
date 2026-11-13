<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Database\Factories;

use App\Modules\Delivery\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryZone> */
class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    public function definition(): array
    {
        return [
            'name' => 'Zone '.fake()->unique()->word(),
            'fee_minor' => 1000,
            'free_above_minor' => 20000,
            'min_order_minor' => 0,
            'is_active' => true,
        ];
    }
}
