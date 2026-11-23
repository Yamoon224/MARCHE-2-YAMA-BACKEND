<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\SaleUnit;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'sale_unit' => SaleUnit::Piece,
            'is_weight_variable' => false,
            'price_minor' => fake()->numberBetween(100, 20000),
            'vat_rate_bp' => 0,
            'perishable' => false,
            'track_stock' => true,
            'is_active' => true,
        ];
    }

    public function perishable(): static
    {
        return $this->state(['perishable' => true]);
    }

    public function byWeight(): static
    {
        return $this->state(['sale_unit' => SaleUnit::Kilogram, 'is_weight_variable' => true]);
    }
}
