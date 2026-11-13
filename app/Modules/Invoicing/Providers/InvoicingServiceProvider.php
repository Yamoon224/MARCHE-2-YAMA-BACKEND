<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Providers;

use App\Modules\Invoicing\Listeners\CreditOnCancellation;
use App\Modules\Invoicing\Listeners\CreditOnManualRefund;
use App\Modules\Invoicing\Listeners\CreditOnPreparationAdjustment;
use App\Modules\Invoicing\Listeners\IssueInvoiceWhenOrderPaid;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Events\OrderTotalAdjusted;
use App\Modules\Payments\Events\RefundIssued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InvoicingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(OrderStatusChanged::class, IssueInvoiceWhenOrderPaid::class);
        Event::listen(OrderStatusChanged::class, CreditOnCancellation::class);
        Event::listen(OrderTotalAdjusted::class, CreditOnPreparationAdjustment::class);
        Event::listen(RefundIssued::class, CreditOnManualRefund::class);
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
