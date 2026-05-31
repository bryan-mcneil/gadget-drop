<?php

use App\Http\Controllers\Api\ReviewedProductsController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')->group(function () {
    Route::get('/reviewed-products', [ReviewedProductsController::class, 'index']);
});
