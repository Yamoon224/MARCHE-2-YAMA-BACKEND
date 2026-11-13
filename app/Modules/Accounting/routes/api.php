<?php

use App\Modules\Accounting\Http\Controllers\TrialBalanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:accounting.view'])
    ->get('accounting/trial-balance', TrialBalanceController::class);
