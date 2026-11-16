<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('jwt')->controller(OrderController::class)->group(function (): void {
    Route::get('orders', 'index');
    Route::post('orders', 'store');
    Route::get('orders/{order}', 'show')->whereUuid('order');
    Route::post('orders/{order}/cancel', 'cancel')->whereUuid('order');
});
