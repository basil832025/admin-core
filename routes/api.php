<?php

use App\Http\Controllers\Api\KioskController;
use App\Http\Middleware\KioskApiKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('kiosk')
    ->middleware(KioskApiKeyMiddleware::class)
    ->group(function (): void {
        Route::get('/categories', [KioskController::class, 'categories']);
        Route::get('/products', [KioskController::class, 'products']);
        Route::post('/orders', [KioskController::class, 'storeOrder']);
    });
