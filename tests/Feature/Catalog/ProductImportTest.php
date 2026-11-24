<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

function importer(): User
{
    return User::factory()->role(Role::CatalogManager)->create();
}

it('requires catalog.manage', function () {
    $this->postJson('/api/v1/catalog/products/import', ['rows' => [['name' => 'X']]])->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/catalog/products/import', ['rows' => [['name' => 'X']]])->assertForbidden();
});

it('creates products, categories and images from normalized rows', function () {
    $this->actingAs(importer(), 'sanctum');

    $report = $this->postJson('/api/v1/catalog/products/import', ['rows' => [
        ['row' => 2, 'name' => 'Riz long parfumé – Tersol – 1kg', 'external_id' => 'a2a29c48-364a', 'price_minor' => 290, 'category' => 'Féculents', 'images' => ['https://images.example.com/a.jpg']],
        ['row' => 3, 'name' => 'Adja bouillon', 'sku' => 'ADJA', 'price_minor' => 270, 'category' => 'Épicerie', 'compare_at_price_minor' => 300],
        ['row' => 4, 'name' => 'Ail Rose', 'category' => 'Féculents', 'is_weight_variable' => true, 'sale_unit' => 'kg'],
    ]])->assertOk()->json('data');

    expect($report['created'])->toBe(3)->and($report['failed'])->toBe(0)
        ->and($report['categories_created'])->toBe(['Féculents', 'Épicerie'])
        ->and($report['warnings'])->toHaveCount(1)->and($report['warnings'][0]['row'])->toBe(4);

    $riz = Product::where('sku', 'IMP-A2A29C48364A')->firstOrFail();
    expect($riz->price_minor)->toBe(290)->and($riz->slug)->toBe('riz-long-parfume-tersol-1kg')->and($riz->images)->toHaveCount(1)
        ->and($riz->image_url)->toBe('https://images.example.com/a.jpg');
    expect(Product::where('sku', 'ADJA')->first()->compare_at_price_minor)->toBe(300);
    expect(Product::where('name', 'Ail Rose')->first())->is_active->toBeFalse()->price_minor->toBe(0);
});

it('updates an existing product by sku without erasing absent fields', function () {
    $this->actingAs(importer(), 'sanctum');
    $category = Category::factory()->create(['name' => 'Laits', 'slug' => 'laits']);
    $product = Product::factory()->create(['sku' => 'ALI', 'category_id' => $category->id, 'price_minor' => 500, 'description' => 'Garder']);

    $report = $this->postJson('/api/v1/catalog/products/import', ['rows' => [['name' => 'Ali Baba', 'sku' => 'ALI', 'price_minor' => 550]]])->json('data');

    expect($report['updated'])->toBe(1)->and($report['created'])->toBe(0);
    $product->refresh();
    expect($product->price_minor)->toBe(550)->and($product->description)->toBe('Garder')->and($product->category_id)->toBe($category->id)->and($product->name)->toBe('Ali Baba');
});

it('reports invalid, duplicated and deleted rows without blocking the others', function () {
    $this->actingAs(importer(), 'sanctum');
    Product::factory()->create(['sku' => 'GONE'])->delete();

    $report = $this->postJson('/api/v1/catalog/products/import', ['rows' => [
        ['row' => 2, 'name' => 'Bon', 'sku' => 'OK1', 'price_minor' => 100],
        ['row' => 3, 'name' => '', 'price_minor' => 100],
        ['row' => 4, 'name' => 'Prix négatif', 'price_minor' => -5],
        ['row' => 5, 'name' => 'Doublon', 'sku' => 'OK1', 'price_minor' => 100],
        ['row' => 6, 'name' => 'Supprimé', 'sku' => 'GONE', 'price_minor' => 100],
    ]])->assertOk()->json('data');

    expect($report['created'])->toBe(1)->and($report['failed'])->toBe(4)->and(array_column($report['errors'], 'row'))->toBe([3, 4, 5, 6]);
    expect(Product::where('sku', 'OK1')->count())->toBe(1);
});

it('keeps slugs unique when two products share a name', function () {
    $this->actingAs(importer(), 'sanctum');

    $report = $this->postJson('/api/v1/catalog/products/import', ['rows' => [
        ['name' => 'Machoiron Fumé', 'external_id' => 'aaa', 'price_minor' => 100],
        ['name' => 'Machoiron Fumé', 'external_id' => 'bbb', 'price_minor' => 200],
    ]])->json('data');

    expect($report['created'])->toBe(2);
    expect(Product::pluck('slug')->unique())->toHaveCount(2);
});

it('saves nothing in dry-run mode', function () {
    $this->actingAs(importer(), 'sanctum');

    $report = $this->postJson('/api/v1/catalog/products/import', ['dry_run' => true, 'rows' => [['name' => 'Fantôme', 'price_minor' => 100, 'category' => 'Nouveau']]])->json('data');

    expect($report['created'])->toBe(1)->and($report['dry_run'])->toBeTrue()->and($report['categories_created'])->toBe(['Nouveau']);
    expect(Product::count())->toBe(0)->and(Category::count())->toBe(0);
});

it('refuses empty and oversized payloads', function () {
    $this->actingAs(importer(), 'sanctum');
    $this->postJson('/api/v1/catalog/products/import', ['rows' => []])->assertUnprocessable();
    $this->postJson('/api/v1/catalog/products/import', ['rows' => array_fill(0, 5001, ['name' => 'x'])])->assertUnprocessable();
});

it('paginates the admin list and filters it by category', function () {
    $this->actingAs(importer(), 'sanctum');
    $a = Category::factory()->create();
    $b = Category::factory()->create();
    Product::factory()->count(3)->create(['category_id' => $a->id]);
    Product::factory()->count(2)->create(['category_id' => $b->id]);

    $this->getJson('/api/v1/catalog/products?per_page=2&page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5)->assertJsonPath('meta.last_page', 3);
    $this->getJson("/api/v1/catalog/products?category_id={$b->id}")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
});
