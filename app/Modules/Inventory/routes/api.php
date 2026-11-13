<?php

use App\Modules\Inventory\Http\Controllers\LotController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('inventory')->group(function () {
    Route::get('lots', [LotController::class, 'index'])->middleware('permission:inventory.view');

    // Le rapport de rappel contient des identifiants de clients : réservé à ceux qui gèrent le stock.
    Route::middleware('permission:inventory.manage')->group(function () {
        Route::post('lots/{lot}/recall', [LotController::class, 'recall'])->whereNumber('lot');
        Route::get('lots/{lot}/recall', [LotController::class, 'report'])->whereNumber('lot');
    });
});
