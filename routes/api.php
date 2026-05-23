<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



// ════════════════════════════════════════════════
// ROUTES AUTH — sous le préfixe /api
// ════════════════════════════════════════════════

Route::post('/login', [AuthenticatedSessionController::class, 'store']);
// POST /api/login → connexion

Route::post('/register', [RegisteredUserController::class, 'store']);
// POST /api/register → inscription

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum');
// POST /api/logout → déconnexion (token requis)

// ... reste de tes routes

// ════════════════════════════════════════════════
// ROUTES PUBLIQUES — accessibles sans être connecté
// ════════════════════════════════════════════════

Route::get('/products', [ProductController::class, 'index']);
// GET /api/products → liste des produits (tout le monde peut voir)

Route::get('/products/{product}', [ProductController::class, 'show']);
// GET /api/products/5 → détail d'un produit
// {product} → Laravel injecte automatiquement l'objet Product (Route Model Binding)

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);


// ════════════════════════════════════════════════
// ROUTES PROTÉGÉES — il faut être connecté
// ════════════════════════════════════════════════

Route::middleware('auth:sanctum')->group(function () {
    // middleware('auth:sanctum') → vérifie que la requête contient un token valide
    // Si pas de token → 401 Unauthorized
    // Si token valide → exécute les routes du groupe
    // group(function() {...}) → regroupe des routes sous le même middleware

    // ── Profil utilisateur ──────────────────────
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    // GET /api/user → infos de l'user connecté

    // ── Panier ──────────────────────────────────
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);

    // ── Commandes ────────────────────────────────
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // ── Réservations ─────────────────────────────
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);


    // ════════════════════════════════════════════════
    // ROUTES ADMIN — réservées aux administrateurs
    // ════════════════════════════════════════════════

    Route::middleware('role:admin')->group(function () {
        // middleware('role:admin') → on va créer ce middleware juste après
        // Vérifie que l'user connecté a le rôle 'admin'
        // Si pas admin → 403 Forbidden

        // ── Gestion des rôles ────────────────────
        Route::apiResource('roles', RoleController::class);
        // apiResource() → crée automatiquement les 5 routes CRUD :
        // GET    /api/roles          → index()
        // POST   /api/roles          → store()
        // GET    /api/roles/{role}   → show()
        // PUT    /api/roles/{role}   → update()
        // DELETE /api/roles/{role}   → destroy()

        // ── Gestion des catégories ───────────────
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        // ── Gestion des produits ─────────────────
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        // ── Gestion des services ─────────────────
        Route::apiResource('services', ServiceController::class)->except(['index', 'show']);
        // except(['index', 'show']) → exclut les routes publiques déjà définies
        // Crée seulement : store(), update(), destroy()

        // ── Toutes les commandes (vue admin) ─────
        Route::get('/admin/orders', [OrderController::class, 'adminIndex']);
        Route::put('/admin/orders/{order}', [OrderController::class, 'updateStatus']);

        // ── Toutes les réservations (vue admin) ──
        Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);
    });
});
