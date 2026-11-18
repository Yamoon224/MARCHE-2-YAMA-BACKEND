<?php

use App\Modules\Promotions\Http\Controllers\PromotionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('promotions')->group(function () {
    // Le client contrôle son code au panier ; limité pour empêcher de deviner des codes.
    Route::post('validate', [PromotionController::class, 'validateCode'])->middleware('throttle:30,1');

    Route::middleware('permission:promotions.manage')->group(function () {
        Route::get('/', [PromotionController::class, 'index']);
        Route::post('/', [PromotionController::class, 'store']);
        Route::get('{promotion}', [PromotionController::class, 'show'])->whereNumber('promotion');
        Route::put('{promotion}', [PromotionController::class, 'update'])->whereNumber('promotion');
        Route::delete('{promotion}', [PromotionController::class, 'destroy'])->whereNumber('promotion');
        Route::post('{promotion}/activate', [PromotionController::class, 'activate'])->whereNumber('promotion');
        Route::post('{promotion}/deactivate', [PromotionController::class, 'deactivate'])->whereNumber('promotion');
    });
});
