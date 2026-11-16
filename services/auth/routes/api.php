<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->controller(AuthController::class)->group(function (): void {
        Route::middleware('throttle:10,1')->group(function (): void {
            Route::post('register', 'register');
            Route::post('login', 'login');
        });

        Route::post('refresh', 'refresh');
        Route::post('logout', 'logout');
    });

    Route::middleware('jwt')->controller(ProfileController::class)->group(function (): void {
        Route::get('users/me', 'show');
        Route::patch('users/me', 'update');
    });
});
