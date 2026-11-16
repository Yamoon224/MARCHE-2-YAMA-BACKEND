<?php

use App\Modules\Catalog\Enums\SaleUnit;
use App\Modules\Catalog\Models\Banner;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Recipe;

it('lists active categories in display order', function () {
    Category::factory()->create(['name' => 'B', 'position' => 2, 'icon' => '🥤']);
    Category::factory()->create(['name' => 'A', 'position' => 1]);
    Category::factory()->create(['name' => 'Cachée', 'position' => 0, 'is_active' => false]);

    $this->getJson('/api/v1/categories')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'A')
        ->assertJsonPath('data.1.icon', '🥤');
});

it('filters products by category slug, featured and promo', function () {
    $fruits = Category::factory()->create(['slug' => 'fruits']);
    Product::factory()->create(['name' => 'Mangue', 'category_id' => $fruits->id, 'is_featured' => true]);
    Product::factory()->create(['name' => 'Riz', 'price_minor' => 500, 'compare_at_price_minor' => 700]);
    Product::factory()->create(['name' => 'Faux promo', 'price_minor' => 500, 'compare_at_price_minor' => 400]);

    $this->getJson('/api/v1/products?category=fruits')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Mangue');
    $this->getJson('/api/v1/products?featured=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_featured', true);
    $this->getJson('/api/v1/products?promo=1')->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Riz')->assertJsonPath('data.0.compare_at_price_minor', 700);
});

it('caps the page size', function () {
    Product::factory()->count(3)->create();

    $this->getJson('/api/v1/products?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.per_page', 2);
    $this->getJson('/api/v1/products?per_page=999')->assertJsonPath('meta.per_page', 50);
});

it('lists only active banners in order', function () {
    Banner::create(['title' => 'Deuxième', 'cta_label' => 'Go', 'cta_url' => '/', 'position' => 2]);
    Banner::create(['title' => 'Première', 'cta_label' => 'Go', 'cta_url' => '/', 'position' => 1]);
    Banner::create(['title' => 'Masquée', 'cta_label' => 'Go', 'cta_url' => '/', 'is_active' => false]);

    $this->getJson('/api/v1/banners')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.title', 'Première');
});

it('lists recipes with their ingredients and an indicative total', function () {
    $kg = Product::factory()->create(['sale_unit' => SaleUnit::Kilogram, 'price_minor' => 900]);
    $piece = Product::factory()->create(['price_minor' => 500]);
    $inactive = Product::factory()->create(['is_active' => false, 'price_minor' => 9999]);
    $recipe = Recipe::create(['name' => 'Sauce', 'slug' => 'sauce']);
    $recipe->items()->create(['product_id' => $kg->id, 'quantity' => 500]);
    $recipe->items()->create(['product_id' => $piece->id, 'quantity' => 2]);
    $recipe->items()->create(['product_id' => $inactive->id, 'quantity' => 1]);

    $this->getJson('/api/v1/recipes')->assertOk()
        ->assertJsonPath('data.0.name', 'Sauce')
        ->assertJsonCount(2, 'data.0.items')
        ->assertJsonPath('data.0.total_minor', 450 + 1000);
});
