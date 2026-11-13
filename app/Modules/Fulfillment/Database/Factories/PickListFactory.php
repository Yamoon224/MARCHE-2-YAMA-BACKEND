<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Database\Factories;

use App\Modules\Fulfillment\Enums\PickListStatus;
use App\Modules\Fulfillment\Models\PickList;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PickList> */
class PickListFactory extends Factory
{
    protected $model = PickList::class;

    public function definition(): array
    {
        return ['order_id' => fake()->unique()->numberBetween(1, 1_000_000), 'status' => PickListStatus::Open];
    }
}
