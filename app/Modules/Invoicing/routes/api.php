<?php

use App\Modules\Invoicing\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('orders/{orderId}/invoice', [InvoiceController::class, 'show'])->whereNumber('orderId');
