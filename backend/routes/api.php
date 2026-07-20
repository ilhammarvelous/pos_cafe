<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\PageNumberController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;

Route::prefix('v1')->group(function () {
    // Auth Public
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:api', 'check.activity'])->group(function () {
        // auth routes
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/activity/heartbeat', [ActivityController::class, 'heartbeat'])->middleware('throttle:6,1');

        // Category Routes
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);

        // Category Management (Manager Only)
        Route::middleware('role:manager')->group(function () {
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
        });

        // Product Routes
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show']);

        // Product Routes (Manager only)
        Route::middleware('role:manager')->group(function () {
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        });

        // Order Routes (Kasir can CREATE, All users can READ) ---
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders', [OrderController::class, 'store'])->middleware('role:kasir');

        // Order Update Routes (Kasir/Barista/Manager)
        Route::put('/orders/{id}', [OrderController::class, 'update'])->middleware('role:kasir,barista,manager');

        // Order Cancel Routes (Kasir/Manager)
        Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->middleware('role:kasir,manager');

        // --- Order Items Endpoints
        Route::get('/orders/{orderId}/items', [OrderItemController::class, 'index']);

        // Mark item as DONE (Barista Only)
        Route::put('/orders/{orderId}/items/{itemId}', [OrderItemController::class, 'update'])->middleware('role:barista');

        // Payment Endpoints
        Route::get('/orders/{orderId}/payments', [PaymentController::class, 'index']);

        // Process Payment (kasir Only)
        Route::post('/orders/{orderId}/payments', [PaymentController::class, 'store'])->middleware('role:kasir');

         // Pager Endpoints
        Route::get('/pagers', [PageNumberController::class, 'index']);
        Route::get('/pagers/{id}', [PageNumberController::class, 'show']);

        // Pager Management (Manager Only)
        Route::middleware('role:manager')->group(function () {
            Route::post('/pagers', [PageNumberController::class, 'store']);
            Route::delete('/pagers/{id}', [PageNumberController::class, 'destroy']);
        });

        // Pager Management (Barista/Kasir)
        Route::middleware('role:barista,kasir')->group(function () {
            Route::put('/pagers/{id}', [PageNumberController::class, 'update']);
            Route::post('/pagers/{id}/ring', [PageNumberController::class, 'ring']);
        });

        // Pager Acknowledge (Public - any authenticated user)
        Route::post('/pagers/{id}/acknowledge', [PageNumberController::class, 'acknowledge']);

         // --- User Management (Manager Only) ---
        Route::middleware('role:manager')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/{id}', [UserController::class, 'show']);
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
        });
    });
});
