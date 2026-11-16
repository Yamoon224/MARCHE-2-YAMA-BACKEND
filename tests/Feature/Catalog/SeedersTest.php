<?php

use App\Modules\Catalog\Models\Banner;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Recipe;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds the storefront catalog and is safe to replay without overwriting edits', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Category::count())->toBe(10)->and(Product::count())->toBeGreaterThanOrEqual(500)->and(Banner::count())->toBe(3)->and(Recipe::count())->toBe(3);

    $count = Product::count();
    Product::where('sku', 'EP-001')->update(['price_minor' => 4900]);
    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe($count)->and(Product::where('sku', 'EP-001')->value('price_minor'))->toBe(4900);
});

it('serves the seeded catalog through the public API', function () {
    $this->seed(DatabaseSeeder::class);

    $this->getJson('/api/v1/categories')->assertJsonCount(10, 'data');
    $this->getJson('/api/v1/products?promo=1&per_page=50')->assertJsonPath('meta.total', fn ($total) => $total >= 20);
    $this->getJson('/api/v1/products?featured=1')->assertJsonPath('meta.total', fn ($total) => $total >= 20);
    $this->getJson('/api/v1/recipes')->assertJsonCount(3, 'data');
});

it('creates no demo account and no known password in production', function () {
    $this->app['env'] = 'production';
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0)->and(Product::count())->toBeGreaterThanOrEqual(500);
});

it('creates the production admin from the environment, with a long enough password only', function () {
    $this->app['env'] = 'production';

    putenv('ADMIN_EMAIL=boss@example.com');
    putenv('ADMIN_PASSWORD=court');
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
    expect(User::count())->toBe(0);

    putenv('ADMIN_PASSWORD=un-mot-de-passe-solide');
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::where('email', 'boss@example.com')->count())->toBe(1)
        ->and(User::where('email', 'boss@example.com')->first()->role)->toBe(Role::Admin);

    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
});
