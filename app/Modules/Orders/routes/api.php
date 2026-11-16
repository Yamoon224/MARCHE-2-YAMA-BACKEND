<?php

use App\Modules\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Suivi sans compte : numéro + e-mail de la commande, limité pour empêcher de deviner des numéros.
Route::post('orders/lookup', [OrderController::class, 'lookup'])->middleware('throttle:8,1');

Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('{order}', [OrderController::class, 'show']);
    Route::post('{order}/transitions', [OrderController::class, 'transition']);
});
