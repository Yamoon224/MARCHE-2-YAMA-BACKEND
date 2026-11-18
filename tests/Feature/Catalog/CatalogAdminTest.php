<?php

use App\Modules\Catalog\Models\Banner;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Recipe;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function manager(): User
{
    return User::factory()->role(Role::CatalogManager)->create();
}

it('requires authentication and catalog.manage for every management route', function () {
    $this->getJson('/api/v1/catalog/categories')->assertUnauthorized();
    $this->postJson('/api/v1/catalog/banners', [])->assertUnauthorized();

    $customer = User::factory()->create();
    $this->actingAs($customer, 'sanctum')->getJson('/api/v1/catalog/products')->assertForbidden();
    $this->actingAs($customer, 'sanctum')->postJson('/api/v1/catalog/media')->assertForbidden();
    $this->actingAs($customer, 'sanctum')->deleteJson('/api/v1/catalog/recipes/'.Recipe::create(['name' => 'R', 'slug' => 'r'])->id)->assertForbidden();
});

it('manages categories and refuses to delete a non-empty one', function () {
    $this->actingAs(manager(), 'sanctum');

    $id = $this->postJson('/api/v1/catalog/categories', ['name' => 'Boissons', 'slug' => 'boissons', 'icon' => '🥤'])
        ->assertCreated()->json('data.id');
    $this->putJson("/api/v1/catalog/categories/{$id}", ['name' => 'Boissons fraîches', 'slug' => 'boissons', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);
    $this->postJson('/api/v1/catalog/categories', ['name' => 'Doublon', 'slug' => 'boissons'])->assertUnprocessable();
    $this->postJson('/api/v1/catalog/categories', ['name' => 'X', 'slug' => 'Pas Valide'])->assertUnprocessable();

    Product::factory()->create(['category_id' => $id]);
    $this->deleteJson("/api/v1/catalog/categories/{$id}")->assertUnprocessable();

    $empty = Category::factory()->create();
    $this->deleteJson("/api/v1/catalog/categories/{$empty->id}")->assertNoContent();
});

it('lists inactive products too and updates price, promo and visibility', function () {
    $this->actingAs(manager(), 'sanctum');
    $product = Product::factory()->create(['is_active' => false, 'price_minor' => 1000]);

    $this->getJson('/api/v1/catalog/products')->assertOk()->assertJsonCount(1, 'data');
    $this->putJson("/api/v1/catalog/products/{$product->id}", [
        'price_minor' => 800, 'compare_at_price_minor' => 1000, 'is_featured' => true, 'is_active' => true,
        'image_url' => 'https://example.test/a.jpg',
    ])->assertOk()->assertJsonPath('data.price_minor', 800)->assertJsonPath('data.compare_at_price_minor', 1000);

    $this->getJson('/api/v1/products?promo=1')->assertJsonCount(1, 'data');
    $this->putJson("/api/v1/catalog/products/{$product->id}", ['price_minor' => 12.5])->assertUnprocessable();
});

it('manages banners and rejects dangerous links', function () {
    $this->actingAs(manager(), 'sanctum');
    $ok = ['title' => 'Promo', 'cta_label' => 'Voir', 'cta_url' => '/rayons'];

    $id = $this->postJson('/api/v1/catalog/banners', $ok)->assertCreated()->json('data.id');
    $this->postJson('/api/v1/catalog/banners', [...$ok, 'cta_url' => 'javascript:alert(1)'])->assertUnprocessable();
    $this->putJson("/api/v1/catalog/banners/{$id}", [...$ok, 'is_active' => false])->assertOk();
    $this->getJson('/api/v1/banners')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/catalog/banners')->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/catalog/banners/{$id}")->assertNoContent();
    expect(Banner::count())->toBe(0);
});

it('creates a recipe with ingredients and replaces them on update', function () {
    $this->actingAs(manager(), 'sanctum');
    [$a, $b] = Product::factory()->count(2)->create(['price_minor' => 500]);

    $id = $this->postJson('/api/v1/catalog/recipes', [
        'name' => 'Sauce', 'slug' => 'sauce',
        'items' => [['product_id' => $a->id, 'quantity' => 2], ['product_id' => $b->id, 'quantity' => 1]],
    ])->assertCreated()->assertJsonCount(2, 'data.items')->assertJsonPath('data.total_minor', 1500)->json('data.id');

    $this->putJson("/api/v1/catalog/recipes/{$id}", ['name' => 'Sauce', 'slug' => 'sauce', 'items' => [['product_id' => $a->id, 'quantity' => 1]]])
        ->assertOk()->assertJsonCount(1, 'data.items');

    $this->postJson('/api/v1/catalog/recipes', ['name' => 'Mauvaise', 'slug' => 'mauvaise', 'items' => [['product_id' => 999, 'quantity' => 1]]])
        ->assertUnprocessable();
    $this->postJson('/api/v1/catalog/recipes', ['name' => 'Doublons', 'slug' => 'doublons', 'items' => [['product_id' => $a->id, 'quantity' => 1], ['product_id' => $a->id, 'quantity' => 1]]])
        ->assertUnprocessable();

    $this->deleteJson("/api/v1/catalog/recipes/{$id}")->assertNoContent();
    expect(Recipe::count())->toBe(0);
});

it('keeps inactive ingredients visible to managers but hidden from the public', function () {
    $inactive = Product::factory()->create(['is_active' => false]);
    $recipe = Recipe::create(['name' => 'R', 'slug' => 'r']);
    $recipe->items()->create(['product_id' => $inactive->id, 'quantity' => 1]);

    $this->getJson('/api/v1/recipes')->assertJsonCount(0, 'data.0.items');
    $this->actingAs(manager(), 'sanctum')->getJson('/api/v1/catalog/recipes')->assertJsonCount(1, 'data.0.items');
});

it('stores an uploaded image and refuses other file types', function () {
    Storage::fake('public');
    $this->actingAs(manager(), 'sanctum');

    $url = $this->postJson('/api/v1/catalog/media', ['image' => UploadedFile::fake()->image('p.jpg')])
        ->assertCreated()->json('data.url');
    expect($url)->toContain('/storage/catalog/');
    Storage::disk('public')->assertCount('catalog', 1);

    $this->postJson('/api/v1/catalog/media', ['image' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')])->assertUnprocessable();
    $this->postJson('/api/v1/catalog/media', ['image' => UploadedFile::fake()->create('x.php', 5, 'text/x-php')])->assertUnprocessable();
});

it('lets marketing manage banners and upload their image, but nothing else in the catalog', function () {
    Storage::fake('public');
    $marketing = User::factory()->role(Role::Marketing)->create();
    $this->actingAs($marketing, 'sanctum');

    $this->getJson('/api/v1/catalog/banners')->assertOk();
    $this->postJson('/api/v1/catalog/media', ['image' => UploadedFile::fake()->image('b.jpg', 800, 400)])->assertCreated();
    $this->postJson('/api/v1/catalog/banners', ['title' => 'Promo', 'cta_label' => 'Voir', 'cta_url' => '/aisles'])->assertCreated();

    $this->getJson('/api/v1/catalog/products')->assertForbidden();
    $this->getJson('/api/v1/catalog/categories')->assertForbidden();
    $this->getJson('/api/v1/catalog/recipes')->assertForbidden();

    $this->actingAs(User::factory()->role(Role::Support)->create(), 'sanctum')->getJson('/api/v1/catalog/banners')->assertForbidden();
});
