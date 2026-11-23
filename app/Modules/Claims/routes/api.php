<?php

use App\Modules\Claims\Http\Controllers\ClaimController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('orders/{orderId}/claims', [ClaimController::class, 'store'])->whereUuid('orderId')->middleware('throttle:10,1');

    Route::get('claims', [ClaimController::class, 'index']);
    Route::get('claims/quality-stats', [ClaimController::class, 'stats'])->middleware('permission:claims.decide');

    Route::scopeBindings()->group(function () {
        Route::get('claims/{claim}', [ClaimController::class, 'show'])->whereNumber('claim');
        Route::get('claims/{claim}/photos/{photo}', [ClaimController::class, 'photo'])->whereNumber(['claim', 'photo']);
        Route::post('claims/{claim}/decision', [ClaimController::class, 'decide'])->whereNumber('claim')->middleware('permission:claims.decide');
    });
});
