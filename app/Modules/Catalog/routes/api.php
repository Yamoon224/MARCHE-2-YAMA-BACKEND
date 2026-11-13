<?php

use App\Modules\Catalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{slug}', [ProductController::class, 'show']);
Route::post('products', [ProductController::class, 'store'])->middleware(['auth:sanctum', 'permission:catalog.manage']);
