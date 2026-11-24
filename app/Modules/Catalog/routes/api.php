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
    Route::post('products', [ProductAdminController::class, 'store']);
    Route::get('products/purge-preview', [ProductAdminController::class, 'purgePreview'])->middleware('permission:users.manage');
    Route::delete('products', [ProductAdminController::class, 'purge'])->middleware('permission:users.manage');
    Route::post('products/import', [ProductAdminController::class, 'import']);
    Route::get('products/{product}', [ProductAdminController::class, 'show'])->whereNumber('product');
    Route::put('products/{product}', [ProductAdminController::class, 'update']);
    Route::delete('products/{product}', [ProductAdminController::class, 'destroy']);
    Route::post('products/{product}/restore', [ProductAdminController::class, 'restore']);
    Route::post('products/{product}/images', [ProductAdminController::class, 'addImages']);
    Route::put('products/{product}/images/order', [ProductAdminController::class, 'reorderImages']);
    Route::post('products/{product}/images/{image}/cover', [ProductAdminController::class, 'setCover']);
    Route::delete('products/{product}/images/{image}', [ProductAdminController::class, 'removeImage']);
    Route::apiResource('categories', CategoryAdminController::class)->except('show');
    Route::apiResource('recipes', RecipeAdminController::class)->except('show');
});

// Bannières : aussi confiées au marketing (banners.manage). L'envoi d'image sert aux produits comme aux bannières.
Route::prefix('catalog')->middleware(['auth:sanctum', 'permission:catalog.manage,banners.manage'])->group(function () {
    Route::apiResource('banners', BannerAdminController::class)->except('show');
    Route::post('media', [MediaController::class, 'store']);
});
