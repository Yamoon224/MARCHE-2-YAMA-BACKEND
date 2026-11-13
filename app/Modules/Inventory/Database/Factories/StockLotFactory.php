<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockLot> */
class StockLotFactory extends Factory
{
    protected $model = StockLot::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id' => fake()->numberBetween(1, 1000),
            'lot_code' => strtoupper(fake()->unique()->bothify('LOT-#####')),
            'expires_on' => now()->addDays(10)->toDateString(),
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'is_blocked' => false,
        ];
    }
}
