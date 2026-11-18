<?php

use App\Modules\Audit\Http\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:audit.view'])->prefix('audit')->group(function () {
    Route::get('/', [AuditController::class, 'index']);
    Route::get('verify', [AuditController::class, 'verify']);
});
