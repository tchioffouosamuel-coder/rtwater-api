<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ClientErrorController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageImportController;
use App\Http\Controllers\Api\ProductImportController;
use App\Http\Controllers\Api\QuoteRequestController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ShippingZoneController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

// ════════════════════════════════════════════════
// AUTH — publiques
// ════════════════════════════════════════════════
// Limitation de débit : bloque le brute-force et le spam de comptes
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

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

// Reglages publics : coordonnees, devise, moyens de paiement actifs
Route::get('/settings', [SettingController::class, 'publicIndex']);

// Zones de livraison et leur tarif pour un panier donne
Route::get('/shipping-zones', [ShippingZoneController::class, 'publicIndex']);

// Verification d'un code promo, et apercu du total avant validation
Route::post('/discounts/check', [DiscountController::class, 'check'])->middleware('throttle:20,1');
Route::post('/orders/quote', [OrderController::class, 'quote'])->middleware('throttle:60,1');

// Lettre d'information — depuis le pied de page du site
Route::post('/newsletter', [NewsletterController::class, 'subscribe'])->middleware('throttle:5,1');
Route::delete('/newsletter', [NewsletterController::class, 'unsubscribe'])->middleware('throttle:5,1');

Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,1');

// Suivi de commande sans compte (référence + téléphone ou e-mail)
Route::get('/orders/track', [OrderController::class, 'track'])->middleware('throttle:10,1');

// Statut via lien signé : utilisé par la fenêtre de paiement, y compris pour les invités
Route::get('/orders/{order}/status', [OrderController::class, 'status'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('orders.status');

// Blog & pages — publics
Route::get('/blog', [BlogPostController::class, 'index']);
Route::get('/blog/{slug}', [BlogPostController::class, 'show']);
Route::get('/pages', [PageController::class, 'index']);
Route::get('/pages/{slug}', [PageController::class, 'show']);

// Formulaire de devis — public (soumis depuis le site vitrine)
Route::post('/quotes', [QuoteRequestController::class, 'store'])->middleware('throttle:5,1');

// Paiement SEBPAY — public (la confirmation USSD est auto-authentifiante)
Route::post('/orders/{order}/pay', [PaymentController::class, 'initiate'])->middleware('throttle:10,1');

// Erreurs JavaScript des visiteurs (journal storage/logs/client-*.log)
Route::post('/client-errors', [ClientErrorController::class, 'store'])->middleware('throttle:20,1');

// Webhook SEBPAY — public, vérifié par HMAC en interne
Route::post('/webhooks/sebpay', [PaymentController::class, 'webhook']);

// ════════════════════════════════════════════════
// PROTÉGÉES — token Sanctum requis
// ════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1');
    Route::get('/me/quotes', [QuoteRequestController::class, 'mine']);

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
    // BACKOFFICE — filtré par permission, pas par nom de rôle
    //
    // Chaque route déclare la capacité qu'elle exige (voir App\Support\Permissions).
    // C'est ce qui rend les rôles gestionnaire / secrétaire / comptable réellement
    // utilisables : avant, tout le backoffice était derrière « role:admin » et ces
    // rôles existaient uniquement dans l'interface React.
    // ════════════════════════════════════════════════

    // Comptes de l'équipe et rôles — administrateur seulement
    Route::middleware('permission:manage_users')->group(function () {
        Route::apiResource('roles', RoleController::class);
    });

    // ── Catalogue ──────────────────────────────────
    Route::middleware('permission:manage_categories')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    });

    Route::middleware('permission:manage_products')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        // Import massif de produits (Excel/CSV + images)
        Route::get('/admin/products/import/template', [ProductImportController::class, 'template']);
        Route::post('/admin/products/import/analyze', [ProductImportController::class, 'analyze'])->middleware('throttle:20,1');
        Route::post('/admin/products/import/commit', [ProductImportController::class, 'commit'])->middleware('throttle:20,1');
        Route::post('/admin/products/import/discard', [ProductImportController::class, 'discard']);
        Route::get('/admin/products/imports', [ProductImportController::class, 'history']);

        // Import d'images en masse : second temps du flux, apres l'Excel
        Route::get('/admin/products/images/pending', [ProductImageImportController::class, 'pending']);
        Route::post('/admin/products/images/analyze', [ProductImageImportController::class, 'analyze'])->middleware('throttle:20,1');
        Route::post('/admin/products/images/commit', [ProductImageImportController::class, 'commit'])->middleware('throttle:20,1');
        Route::post('/admin/products/images/discard', [ProductImageImportController::class, 'discard']);
    });

    Route::middleware('permission:manage_services')->group(function () {
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
    });

    // Upload d'images : commun au catalogue, aux pages et au blog
    Route::middleware('permission:manage_products,manage_services,manage_pages,manage_blog')->group(function () {
        Route::post('/upload/image', [UploadController::class, 'uploadImage']);
        Route::delete('/upload/image', [UploadController::class, 'deleteImage']);
    });

    // ── Commandes & clients ────────────────────────
    Route::middleware('permission:manage_orders')->group(function () {
        Route::get('/admin/orders', [OrderController::class, 'adminIndex']);
        Route::put('/admin/orders/{order}', [OrderController::class, 'updateStatus']);
        Route::get('/admin/orders/{order}/receipt', [OrderController::class, 'downloadReceipt']);
        Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);
        Route::get('/admin/quotes', [QuoteRequestController::class, 'index']);
        Route::get('/admin/quotes/{quoteRequest}', [QuoteRequestController::class, 'show']);
        Route::put('/admin/quotes/{quoteRequest}', [QuoteRequestController::class, 'update']);
    });

    Route::middleware('permission:manage_customers')->group(function () {
        // L'export passe avant la route paramétrée, sinon « export » serait pris
        // pour un identifiant de client.
        Route::get('/admin/customers/export', [CustomerController::class, 'export']);
        Route::get('/admin/customers', [CustomerController::class, 'index']);
        Route::get('/admin/customers/{user}', [CustomerController::class, 'show']);
    });

    // ── Caisse & stock ─────────────────────────────
    Route::middleware('permission:manage_pos')->group(function () {
        Route::post('/admin/pos-order', [OrderController::class, 'posStore']);
    });

    Route::middleware('permission:manage_inventory')->group(function () {
        Route::get('/admin/stock-movements', [StockMovementController::class, 'index']);
        Route::post('/admin/stock-movements', [StockMovementController::class, 'store']);
    });

    Route::middleware('permission:manage_receipts')->group(function () {
        Route::get('/admin/invoices', [InvoiceController::class, 'index']);
        Route::post('/admin/invoices', [InvoiceController::class, 'store']);
        Route::get('/admin/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::put('/admin/invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::get('/admin/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
    });

    // ── Contenu ────────────────────────────────────
    Route::middleware('permission:manage_blog')->group(function () {
        Route::get('/admin/blog', [BlogPostController::class, 'adminIndex']);
        Route::post('/admin/blog', [BlogPostController::class, 'store']);
        Route::put('/admin/blog/{blogPost}', [BlogPostController::class, 'update']);
        Route::delete('/admin/blog/{blogPost}', [BlogPostController::class, 'destroy']);
    });

    Route::middleware('permission:manage_pages')->group(function () {
        Route::get('/admin/pages', [PageController::class, 'adminIndex']);
        Route::post('/admin/pages', [PageController::class, 'store']);
        Route::put('/admin/pages/{page}', [PageController::class, 'update']);
        Route::delete('/admin/pages/{page}', [PageController::class, 'destroy']);
    });

    Route::middleware('permission:manage_reviews')->group(function () {
        Route::get('/admin/reviews', [ReviewController::class, 'adminIndex']);
        Route::put('/admin/reviews/{review}', [ReviewController::class, 'adminUpdate']);
        Route::delete('/admin/reviews/{review}', [ReviewController::class, 'adminDestroy']);
    });

    // ── Commercial ─────────────────────────────────
    Route::middleware('permission:manage_discounts')->group(function () {
        Route::get('/admin/discounts', [DiscountController::class, 'index']);
        Route::post('/admin/discounts', [DiscountController::class, 'store']);
        Route::put('/admin/discounts/{discount}', [DiscountController::class, 'update']);
        Route::delete('/admin/discounts/{discount}', [DiscountController::class, 'destroy']);
    });

    Route::middleware('permission:manage_shipping')->group(function () {
        Route::get('/admin/shipping-zones', [ShippingZoneController::class, 'index']);
        Route::post('/admin/shipping-zones', [ShippingZoneController::class, 'store']);
        Route::put('/admin/shipping-zones/{shippingZone}', [ShippingZoneController::class, 'update']);
        Route::delete('/admin/shipping-zones/{shippingZone}', [ShippingZoneController::class, 'destroy']);
    });

    Route::middleware('permission:manage_newsletter')->group(function () {
        Route::get('/admin/newsletter/export', [NewsletterController::class, 'export']);
        Route::get('/admin/newsletter', [NewsletterController::class, 'index']);
    });

    // ── Pilotage ───────────────────────────────────
    Route::middleware('permission:view_analytics')->group(function () {
        Route::get('/admin/analytics', [AnalyticsController::class, 'index']);
    });

    Route::middleware('permission:manage_settings')->group(function () {
        Route::get('/admin/settings', [SettingController::class, 'index']);
        Route::put('/admin/settings', [SettingController::class, 'update']);
    });
});
