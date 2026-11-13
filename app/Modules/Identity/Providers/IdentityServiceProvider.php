<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Contracts\ContactDirectory;
use App\Modules\Identity\Contracts\CourierDirectory;
use App\Modules\Identity\Services\EloquentContactDirectory;
use App\Modules\Identity\Http\Middleware\RequirePermission;
use App\Modules\Identity\Services\EloquentCourierDirectory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CourierDirectory::class, EloquentCourierDirectory::class);
        $this->app->bind(ContactDirectory::class, EloquentContactDirectory::class);
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $router->aliasMiddleware('permission', RequirePermission::class);
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
