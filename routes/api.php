
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\UploadController;

Route::middleware('auth:sanctum')->group(function () {

    // ... routes existantes ...

    // ── Upload (admin uniquement) ────────────────
    Route::middleware('role:admin')->group(function () {

        Route::post('/upload/image', [UploadController::class, 'uploadImage']);
        // POST /api/upload/image → uploader une image

        Route::delete('/upload/image', [UploadController::class, 'deleteImage']);
        // DELETE /api/upload/image → supprimer une image

        // ... autres routes admin
    });
});

// ════════════════════════════════════════════════
// AUTH — publiques
// ════════════════════════════════════════════════
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// ════════════════════════════════════════════════
// PUBLIQUES — sans token
// ════════════════════════════════════════════════
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

// ════════════════════════════════════════════════
// PROTÉGÉES — token requis
// ════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Panier
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);

    // Commandes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // Réservations
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);

    // ════════════════════════════════════════════════
    // ADMIN — token admin requis
    // ════════════════════════════════════════════════
    Route::middleware('role:admin')->group(function () {

        // Rôles
        Route::apiResource('roles', RoleController::class);

        // Catégories
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        // Produits
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        // Services
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

        // Vue admin
        Route::get('/admin/orders', [OrderController::class, 'adminIndex']);
        Route::put('/admin/orders/{order}', [OrderController::class, 'updateStatus']);
        Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);
    });
});
