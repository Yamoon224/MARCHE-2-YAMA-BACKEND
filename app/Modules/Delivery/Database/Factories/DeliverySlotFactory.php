<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Database\Factories;

use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Delivery\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliverySlot> */
class DeliverySlotFactory extends Factory
{
    protected $model = DeliverySlot::class;

    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'delivery_zone_id' => DeliveryZone::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'capacity' => 5,
            'booked' => 0,
        ];
    }
}
