<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return ['name' => 'Magasin '.fake()->city(), 'code' => strtoupper(fake()->unique()->bothify('MG-###')), 'is_active' => true];
    }
}
