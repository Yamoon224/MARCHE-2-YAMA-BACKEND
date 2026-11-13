<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Providers;

use App\Modules\Fulfillment\Listeners\CreatePickListWhenPreparing;
use App\Modules\Orders\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FulfillmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(OrderStatusChanged::class, CreatePickListWhenPreparing::class);
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
