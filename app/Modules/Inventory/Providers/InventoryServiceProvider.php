<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Listeners\SyncStockWithOrderStatus;
use App\Modules\Inventory\Services\InventoryStockAlertReader;
use App\Modules\Inventory\Services\InventoryStockReservationPort;
use App\Modules\Orders\Contracts\StockReservationPort;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Reporting\Contracts\StockAlertReader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockAlertReader::class, InventoryStockAlertReader::class);
        $this->app->bind(StockReservationPort::class, InventoryStockReservationPort::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(OrderStatusChanged::class, SyncStockWithOrderStatus::class);
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
