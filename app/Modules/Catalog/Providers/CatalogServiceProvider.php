<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Services\EloquentCatalogPriceReader;
use App\Modules\Catalog\Services\EloquentProductCategoryReader;
use App\Modules\Orders\Contracts\CatalogPriceReader;
use App\Modules\Reporting\Contracts\ProductCategoryReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductCategoryReader::class, EloquentProductCategoryReader::class);
        $this->app->bind(CatalogPriceReader::class, EloquentCatalogPriceReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
