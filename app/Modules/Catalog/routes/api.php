<?php

use App\Modules\Catalog\Http\Controllers\Admin\BannerAdminController;
use App\Modules\Catalog\Http\Controllers\Admin\CategoryAdminController;
use App\Modules\Catalog\Http\Controllers\Admin\MediaController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductAdminController;
use App\Modules\Catalog\Http\Controllers\Admin\RecipeAdminController;
use App\Modules\Catalog\Http\Controllers\BannerController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{slug}', [ProductController::class, 'show']);
Route::post('products', [ProductController::class, 'store'])->middleware(['auth:sanctum', 'permission:catalog.manage']);
Route::get('categories', [CategoryController::class, 'index']);
Route::get('banners', [BannerController::class, 'index']);
Route::get('recipes', [RecipeController::class, 'index']);

// Gestion du contenu de la vitrine (rôles disposant de catalog.manage).
Route::prefix('catalog')->middleware(['auth:sanctum', 'permission:catalog.manage'])->group(function () {
    Route::get('products', [ProductAdminController::class, 'index']);
    Route::put('products/{product}', [ProductAdminController::class, 'update']);
    Route::apiResource('categories', CategoryAdminController::class)->except('show');
    Route::apiResource('banners', BannerAdminController::class)->except('show');
    Route::apiResource('recipes', RecipeAdminController::class)->except('show');
    Route::post('media', [MediaController::class, 'store']);
});
