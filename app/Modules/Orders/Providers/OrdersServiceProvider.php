<?php

declare(strict_types=1);

namespace App\Modules\Orders\Providers;

use App\Modules\Orders\Contracts\OrderClaimPort;
use App\Modules\Orders\Contracts\OrderCustomerDirectory;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use App\Modules\Orders\Contracts\OrderInvoiceReader;
use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Orders\Contracts\OrderPickingPort;
use App\Modules\Orders\Contracts\PosOrderPort;
use App\Modules\Orders\Services\EloquentOrderClaimPort;
use App\Modules\Orders\Services\EloquentOrderCustomerDirectory;
use App\Modules\Orders\Services\EloquentOrderDeliveryPort;
use App\Modules\Orders\Services\EloquentOrderInvoiceReader;
use App\Modules\Orders\Services\EloquentOrderPaymentPort;
use App\Modules\Orders\Services\EloquentOrderPickingPort;
use App\Modules\Orders\Services\EloquentPosOrderPort;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderPaymentPort::class, EloquentOrderPaymentPort::class);
        $this->app->bind(OrderInvoiceReader::class, EloquentOrderInvoiceReader::class);
        $this->app->bind(OrderPickingPort::class, EloquentOrderPickingPort::class);
        $this->app->bind(OrderDeliveryPort::class, EloquentOrderDeliveryPort::class);
        $this->app->bind(OrderClaimPort::class, EloquentOrderClaimPort::class);
        $this->app->bind(OrderCustomerDirectory::class, EloquentOrderCustomerDirectory::class);
        $this->app->bind(PosOrderPort::class, EloquentPosOrderPort::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
