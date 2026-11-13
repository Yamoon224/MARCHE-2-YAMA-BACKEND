<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Providers;

use App\Modules\Accounting\Listeners\PostCashHandoverEntry;
use App\Modules\Accounting\Listeners\PostCreditNoteEntry;
use App\Modules\Accounting\Listeners\PostInvoiceEntry;
use App\Modules\Accounting\Listeners\PostPaymentEntry;
use App\Modules\Accounting\Listeners\PostRefundEntry;
use App\Modules\Delivery\Events\CashHandedOver;
use App\Modules\Invoicing\Events\CreditNoteIssued;
use App\Modules\Invoicing\Events\InvoiceIssued;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Payments\Events\RefundIssued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccountingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(InvoiceIssued::class, PostInvoiceEntry::class);
        Event::listen(CreditNoteIssued::class, PostCreditNoteEntry::class);
        Event::listen(PaymentSucceeded::class, PostPaymentEntry::class);
        Event::listen(RefundIssued::class, PostRefundEntry::class);
        Event::listen(CashHandedOver::class, PostCashHandoverEntry::class);
        Route::prefix('api/v1')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
