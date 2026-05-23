<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    // Affiche le panier de l'utilisateur connecté
    {
        $cartItems = $request->user()->cartItems()->with('product.category')->get();
        // $request->user() → retourne l'User connecté (via le token Sanctum)
        // ->cartItems()    → accède à la relation hasMany
        // ->with('product.category')
        //    → charge le produit ET sa catégorie en même temps
        //    → product.category = relation imbriquée (dot notation)
        //    → 1 seule requête SQL supplémentaire pour tout charger
        // ->get()          → exécute la requête

        $total = $cartItems->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });
        // sum() → méthode de Collection Laravel
        // Itère sur chaque CartItem et additionne les résultats
        // Calcule le total du panier en PHP (pas en SQL)

        return response()->json([
            'data'  => CartItemResource::collection($cartItems),
            'total' => (float) $total,
            // Total du panier
        ]);
    }

    public function store(Request $request): JsonResponse
    // Ajoute un produit au panier
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $product = Product::find($validated['product_id']);

        if ($product->stock < $validated['quantity']) {
            return response()->json([
                'message' => 'Stock insuffisant. Stock disponible : ' . $product->stock,
            ], 422);
            // 422 = Unprocessable Entity
            // La requête est valide mais la logique métier l'interdit
        }

        $cartItem = CartItem::updateOrCreate(
            [
                'user_id'    => $request->user()->id,
                'product_id' => $validated['product_id'],
            ],
            // Premier argument : conditions de recherche
            // Cherche un CartItem avec cet user_id ET ce product_id

            [
                'quantity' => $validated['quantity'],
            ]
            // Deuxième argument : données à mettre à jour ou créer
            //
            // Si trouvé → UPDATE cart_items SET quantity = ? WHERE ...
            // Si pas trouvé → INSERT INTO cart_items (...) VALUES (...)
            //
            // Gère automatiquement la contrainte unique(user_id, product_id)
            // Un user ne peut pas avoir deux lignes pour le même produit
        );

        $cartItem->load('product');

        return response()->json([
            'message' => 'Produit ajouté au panier',
            'data'    => new CartItemResource($cartItem),
        ], 201);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    // Modifie la quantité d'un article du panier
    {
        if ($cartItem->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée',
            ], 403);
            // Vérifie que le cartItem appartient à l'user connecté
            // Un user ne peut pas modifier le panier d'un autre !
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cartItem->update($validated);
        $cartItem->load('product');

        return response()->json([
            'message' => 'Quantité mise à jour',
            'data'    => new CartItemResource($cartItem),
        ]);
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    // Supprime un article du panier
    {
        if ($cartItem->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée',
            ], 403);
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'Article retiré du panier',
        ]);
    }
}
