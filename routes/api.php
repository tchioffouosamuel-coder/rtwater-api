<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QuoteRequestController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

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
Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

Route::post('/orders', [OrderController::class, 'store']);

// Blog & pages — publics
Route::get('/blog', [BlogPostController::class, 'index']);
Route::get('/blog/{slug}', [BlogPostController::class, 'show']);
Route::get('/pages', [PageController::class, 'index']);
Route::get('/pages/{slug}', [PageController::class, 'show']);

// Formulaire de devis — public (soumis depuis le site vitrine)
Route::post('/quotes', [QuoteRequestController::class, 'store']);

// Paiement SEBPAY — public (la confirmation USSD est auto-authentifiante)
Route::post('/orders/{order}/pay', [PaymentController::class, 'initiate']);

// Webhook SEBPAY — public, vérifié par HMAC en interne
Route::post('/webhooks/sebpay', [PaymentController::class, 'webhook']);

// ════════════════════════════════════════════════
// PROTÉGÉES — token Sanctum requis
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

    // Commandes (utilisateur connecté)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/receipt', [OrderController::class, 'downloadReceipt']);

    // Avis produits (authentifié)
    Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);

    // Réservations
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);

    // ════════════════════════════════════════════════
    // ADMIN — rôle admin requis
    // ════════════════════════════════════════════════
    Route::middleware('role:admin')->group(function () {

        // Rôles
        Route::apiResource('roles', RoleController::class);

        // Catégories (écriture)
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        // Produits (écriture)
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        // Services (écriture)
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

        // Upload images
        Route::post('/upload/image', [UploadController::class, 'uploadImage']);
        Route::delete('/upload/image', [UploadController::class, 'deleteImage']);

        // Commandes admin
        Route::get('/admin/orders', [OrderController::class, 'adminIndex']);
        Route::put('/admin/orders/{order}', [OrderController::class, 'updateStatus']);
        Route::post('/admin/pos-order', [OrderController::class, 'posStore']);
        Route::get('/admin/orders/{order}/receipt', [OrderController::class, 'downloadReceipt']);

        // Réservations admin
        Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);

        // Demandes de devis admin
        Route::get('/admin/quotes', [QuoteRequestController::class, 'index']);
        Route::get('/admin/quotes/{quoteRequest}', [QuoteRequestController::class, 'show']);
        Route::put('/admin/quotes/{quoteRequest}', [QuoteRequestController::class, 'update']);

        // Blog admin
        Route::get('/admin/blog', [BlogPostController::class, 'adminIndex']);
        Route::post('/admin/blog', [BlogPostController::class, 'store']);
        Route::put('/admin/blog/{blogPost}', [BlogPostController::class, 'update']);
        Route::delete('/admin/blog/{blogPost}', [BlogPostController::class, 'destroy']);

        // Pages CMS admin
        Route::get('/admin/pages', [PageController::class, 'adminIndex']);
        Route::post('/admin/pages', [PageController::class, 'store']);
        Route::put('/admin/pages/{page}', [PageController::class, 'update']);
        Route::delete('/admin/pages/{page}', [PageController::class, 'destroy']);

        // Factures & devis
        Route::get('/admin/invoices', [InvoiceController::class, 'index']);
        Route::post('/admin/invoices', [InvoiceController::class, 'store']);
        Route::get('/admin/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::put('/admin/invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::get('/admin/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);

        // Mouvements de stock
        Route::get('/admin/stock-movements', [StockMovementController::class, 'index']);
        Route::post('/admin/stock-movements', [StockMovementController::class, 'store']);

        // Avis produits — admin
        Route::get('/admin/reviews', [ReviewController::class, 'adminIndex']);
        Route::put('/admin/reviews/{review}', [ReviewController::class, 'adminUpdate']);
        Route::delete('/admin/reviews/{review}', [ReviewController::class, 'adminDestroy']);

        // Analytics
        Route::get('/admin/analytics', [AnalyticsController::class, 'index']);
    });
});
