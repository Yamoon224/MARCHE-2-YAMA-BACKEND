<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Providers;

use App\Modules\Delivery\Listeners\CreateDeliveryWhenReady;
use App\Modules\Delivery\Listeners\ReleaseSlotWhenOrderEnds;
use App\Modules\Delivery\Services\DeliveryOrderDetailProvider;
use App\Modules\Delivery\Services\EloquentDeliveryAlertReader;
use App\Modules\Delivery\Services\EloquentDeliveryPort;
use App\Modules\Orders\Contracts\DeliveryPort;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Reporting\Contracts\DeliveryAlertReader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeliveryAlertReader::class, EloquentDeliveryAlertReader::class);
        $this->app->bind(DeliveryPort::class, EloquentDeliveryPort::class);
        $this->app->tag(DeliveryOrderDetailProvider::class, 'order.details');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(OrderStatusChanged::class, ReleaseSlotWhenOrderEnds::class);
        Event::listen(OrderStatusChanged::class, CreateDeliveryWhenReady::class);
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
