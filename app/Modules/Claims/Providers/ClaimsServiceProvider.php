<?php

declare(strict_types=1);

namespace App\Modules\Claims\Providers;

use App\Modules\Claims\Listeners\MarkClaimRefundCompleted;
use App\Modules\Payments\Events\RefundIssued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ClaimsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(RefundIssued::class, MarkClaimRefundCompleted::class);
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
