<?php

use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Payments\Http\Controllers\RefundController;
use App\Modules\Payments\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('orders/{orderId}/payments', [PaymentController::class, 'store'])->whereNumber('orderId')->middleware('throttle:30,1');
    Route::post('payments/{payment}/validate-cash-on-delivery', [PaymentController::class, 'validateCashOnDelivery'])
        ->middleware('permission:orders.manage');

    Route::get('orders/{orderId}/refunds', [RefundController::class, 'index'])->whereNumber('orderId');
    Route::post('orders/{orderId}/refunds', [RefundController::class, 'store'])->whereNumber('orderId')
        ->middleware(['permission:payments.refund', 'throttle:30,1']);
});

Route::post('webhooks/payments/{provider}', [WebhookController::class, 'handle'])->middleware('throttle:300,1');
