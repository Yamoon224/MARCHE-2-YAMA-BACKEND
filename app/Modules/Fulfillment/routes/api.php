<?php

use App\Modules\Fulfillment\Http\Controllers\PickListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:orders.prepare'])->prefix('fulfillment')->group(function () {
    Route::get('pick-lists', [PickListController::class, 'index']);

    Route::scopeBindings()->group(function () {
        Route::get('pick-lists/{pickList}', [PickListController::class, 'show']);
        Route::post('pick-lists/{pickList}/start', [PickListController::class, 'start']);
        Route::post('pick-lists/{pickList}/lines/{line}/pick', [PickListController::class, 'pick']);
        Route::post('pick-lists/{pickList}/lines/{line}/substitute', [PickListController::class, 'substitute']);
        Route::post('pick-lists/{pickList}/complete', [PickListController::class, 'complete']);
    });
});
