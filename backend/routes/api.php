<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;

Route::prefix('v1')->group(function () {
    // Auth Public
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected auth routes
    Route::middleware(['auth:api', 'check.activity'])->group(function () {
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/activity/heartbeat', [ActivityController::class, 'heartbeat'])->middleware('throttle:6,1');
    });

    // Protected product routes
    Route::middleware(['auth:api', 'check.activity'])->group(function () {
         // --- Product Routes (All users can READ) ---
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show']);

        // --- Product Routes (Manager only - CREATE/UPDATE/DELETE) ---
        Route::middleware('role:MANAGER')->group(function () {
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        });
    });

     // Protected order routes
    Route::middleware(['auth:api', 'check.activity'])->group(function () {
        // --- Order Routes (Kasir can CREATE, All users can READ) ---
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders', [OrderController::class, 'store'])->middleware('role:kasir');

        // --- Order Update Routes (Kasir/Barista/Manager) ---
        Route::put('/orders/{id}', [OrderController::class, 'update'])
            ->middleware('role:kasir,barista,manager');

        // --- Order Cancel Routes (Kasir/Manager) ---
        Route::delete('/orders/{id}', [OrderController::class, 'destroy'])
            ->middleware('role:kasir,manager');
    });
});
