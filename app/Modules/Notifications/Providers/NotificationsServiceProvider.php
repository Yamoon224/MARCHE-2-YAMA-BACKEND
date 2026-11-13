<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Providers;

use App\Modules\Inventory\Events\LotRecalled;
use App\Modules\Notifications\Listeners\NotifyOnLotRecall;
use App\Modules\Notifications\Listeners\NotifyOnOrderStatus;
use App\Modules\Notifications\Listeners\NotifyOnRefund;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Payments\Events\RefundIssued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(OrderStatusChanged::class, NotifyOnOrderStatus::class);
        Event::listen(RefundIssued::class, NotifyOnRefund::class);
        Event::listen(LotRecalled::class, NotifyOnLotRecall::class);
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
