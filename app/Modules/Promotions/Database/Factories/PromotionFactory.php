<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Database\Factories;

use App\Modules\Promotions\Enums\PromotionType;
use App\Modules\Promotions\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Promotion> */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO##??')),
            'description' => null,
            'type' => PromotionType::Percent,
            'value' => 10,
            'min_order_minor' => 0,
            'max_uses' => null,
            'used_count' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }
}
