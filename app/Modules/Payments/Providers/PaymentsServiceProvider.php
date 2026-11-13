<?php

declare(strict_types=1);

namespace App\Modules\Payments\Providers;

use App\Modules\Delivery\Events\DeliveryCompleted;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Events\OrderTotalAdjusted;
use App\Modules\Payments\Contracts\CashDueReader;
use App\Modules\Payments\Listeners\CollectCashWhenDelivered;
use App\Modules\Payments\Listeners\RefundOnCancellation;
use App\Modules\Payments\Listeners\RefundOnPreparationAdjustment;
use App\Modules\Payments\Services\PaymentsCashDueReader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CashDueReader::class, PaymentsCashDueReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(OrderTotalAdjusted::class, RefundOnPreparationAdjustment::class);
        Event::listen(OrderStatusChanged::class, RefundOnCancellation::class);
        Event::listen(DeliveryCompleted::class, CollectCashWhenDelivered::class);
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
