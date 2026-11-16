<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProxyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class);

    Route::any('{resource}/{path?}', ProxyController::class)
        ->where('path', '.*')
        ->middleware('throttle:api');
});
