<?php

use App\Modules\Delivery\Http\Controllers\CashController;
use App\Modules\Delivery\Http\Controllers\CourierController;
use App\Modules\Delivery\Http\Controllers\DeliveryController;
use App\Modules\Delivery\Http\Controllers\DispatchController;
use App\Modules\Delivery\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('delivery')->group(function () {
    Route::get('zones', [DeliveryController::class, 'zones']);
    Route::get('zones/{zone}/slots', [DeliveryController::class, 'slots'])->whereNumber('zone');

    Route::middleware(['auth:sanctum', 'permission:delivery.manage'])->group(function () {
        Route::post('zones', [DeliveryController::class, 'storeZone']);
        Route::post('slots', [DeliveryController::class, 'storeSlot']);
    });

    // Régulation : affecter, retourner, débloquer.
    Route::middleware(['auth:sanctum', 'permission:delivery.assign'])->group(function () {
        Route::get('deliveries', [DispatchController::class, 'index']);
        Route::get('couriers', [DispatchController::class, 'couriers']);
        Route::post('deliveries/{delivery}/assign', [DispatchController::class, 'assign']);
        Route::post('deliveries/{delivery}/return', [DispatchController::class, 'returnToStore']);
        Route::post('deliveries/{delivery}/unlock', [DispatchController::class, 'unlock']);
    });

    // Remise de fonds : réservée à qui peut recevoir de l'argent en caisse.
    Route::middleware(['auth:sanctum', 'permission:cash.receive'])->prefix('cash')->group(function () {
        Route::get('/', [CashController::class, 'index']);
        Route::get('handovers', [CashController::class, 'handovers']);
        Route::post('handovers', [CashController::class, 'store'])->middleware('throttle:60,1');
    });

    // Livreur : tournée, départ, position, remise, échec.
    Route::middleware(['auth:sanctum', 'permission:delivery.courier'])->group(function () {
        Route::get('my-tour', [CourierController::class, 'tour']);
        Route::get('my-cash', [CashController::class, 'mine']);
        Route::post('deliveries/{delivery}/start', [CourierController::class, 'start']);
        Route::post('deliveries/{delivery}/location', [CourierController::class, 'location'])->middleware('throttle:120,1');
        Route::post('deliveries/{delivery}/confirm', [CourierController::class, 'confirm'])->middleware('throttle:30,1');
        Route::post('deliveries/{delivery}/fail', [CourierController::class, 'fail']);
    });
});

Route::middleware('auth:sanctum')->get('orders/{orderId}/tracking', [TrackingController::class, 'show'])->whereNumber('orderId');
