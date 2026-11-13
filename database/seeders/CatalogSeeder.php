<?php

namespace Database\Seeders;

use App\Modules\Catalog\Enums\SaleUnit;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $fruits = Category::firstOrCreate(['slug' => 'fruits-legumes'], ['name' => 'Fruits et légumes']);
        $epicerie = Category::firstOrCreate(['slug' => 'epicerie'], ['name' => 'Épicerie']);

        $items = [
            [$fruits, 'FL-001', 'Tomates fraîches', 'tomates-fraiches', SaleUnit::Kilogram, 900, true],
            [$fruits, 'FL-002', 'Banane plantain', 'banane-plantain', SaleUnit::Kilogram, 700, true],
            [$epicerie, 'EP-001', 'Riz parfumé 5 kg', 'riz-parfume-5kg', SaleUnit::Piece, 5500, false],
            [$epicerie, 'EP-002', 'Huile végétale 1 L', 'huile-vegetale-1l', SaleUnit::Piece, 1500, false],
        ];

        foreach ($items as [$category, $sku, $name, $slug, $unit, $price, $perishable]) {
            Product::firstOrCreate(['sku' => $sku], [
                'category_id' => $category->id, 'name' => $name, 'slug' => $slug, 'sale_unit' => $unit,
                'is_weight_variable' => $unit === SaleUnit::Kilogram, 'price_minor' => $price,
                'perishable' => $perishable,
            ]);
        }
    }
}
