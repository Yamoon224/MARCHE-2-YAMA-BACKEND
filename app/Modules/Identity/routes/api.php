<?php

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\CustomerController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:20,1');
    Route::post('guest', [AuthController::class, 'guest'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('claim-account', [AuthController::class, 'claimAccount'])->middleware('throttle:10,1');
        Route::get('me', [AuthController::class, 'me']);
        Route::put('me', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:10,1');
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('staff/{user}/reactivate', [StaffController::class, 'reactivate'])->whereNumber('user');
        Route::apiResource('staff', StaffController::class)->parameters(['staff' => 'user'])->whereNumber('user');
    });

    Route::middleware('permission:customers.view')->group(function () {
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{user}', [CustomerController::class, 'show'])->whereNumber('user');
    });
});
