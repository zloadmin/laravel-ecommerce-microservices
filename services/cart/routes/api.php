<?php

use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/cart')->controller(CartController::class)->group(function (): void {
    // Customers are identified by their token, guests by the X-Cart-Id header.
    Route::middleware('jwt:optional')->group(function (): void {
        Route::get('/', 'show');
        Route::delete('/', 'clear');
        Route::post('items', 'add');
        Route::put('items/{product}', 'set');
        Route::delete('items/{product}', 'remove');
    });

    Route::middleware('jwt')->group(function (): void {
        Route::post('merge', 'merge');
        Route::post('checkout', 'checkout');
    });
});
