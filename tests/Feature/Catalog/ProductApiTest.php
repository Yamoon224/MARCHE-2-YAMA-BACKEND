<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

function productPayload(array $overrides = []): array
{
    return [
        'category_id' => Category::factory()->create()->id,
        'sku' => 'SKU-001', 'name' => 'Tomate', 'slug' => 'tomate',
        'sale_unit' => 'kg', 'is_weight_variable' => true, 'price_minor' => 1200, 'perishable' => true,
        ...$overrides,
    ];
}

it('lists only active products publicly', function () {
    Product::factory()->create(['name' => 'Actif']);
    Product::factory()->create(['name' => 'Inactif', 'is_active' => false]);

    $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Actif');
});

it('filters products by search term', function () {
    Product::factory()->create(['name' => 'Riz parfumé']);
    Product::factory()->create(['name' => 'Huile']);

    $this->getJson('/api/v1/products?q=riz')->assertOk()->assertJsonCount(1, 'data');
});

it('shows a product by slug and 404s otherwise', function () {
    Product::factory()->create(['slug' => 'manioc']);

    $this->getJson('/api/v1/products/manioc')->assertOk()->assertJsonPath('data.slug', 'manioc');
    $this->getJson('/api/v1/products/absent')->assertNotFound();
});

it('lets a catalog manager create a product with an integer price', function () {
    $this->actingAs(User::factory()->role(Role::CatalogManager)->create(), 'sanctum')
        ->postJson('/api/v1/products', productPayload())
        ->assertCreated()->assertJsonPath('data.price_minor', 1200);
});

it('forbids customers and guests from creating products', function () {
    $this->postJson('/api/v1/products', productPayload())->assertUnauthorized();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/products', productPayload())->assertForbidden();
});

it('rejects a negative or fractional price', function () {
    $this->actingAs(User::factory()->role(Role::Admin)->create(), 'sanctum')
        ->postJson('/api/v1/products', productPayload(['price_minor' => 12.5]))
        ->assertUnprocessable()->assertJsonValidationErrors('price_minor');
});
