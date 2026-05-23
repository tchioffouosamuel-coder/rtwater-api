<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    // Liste les commandes de l'utilisateur connecté
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            // Filtre strict : l'user ne voit QUE ses propres commandes
            // Un user ne peut jamais voir les commandes des autres

            ->with('items.product')
            // Charge les articles ET leurs produits en même temps
            // items        → relation hasMany vers OrderItem
            // items.product → relation imbriquée : OrderItem → Product
            // 3 requêtes au total, peu importe le nombre de commandes

            ->latest()
            // Trie par created_at DESC → les plus récentes en premier
            // Equivalent à : ->orderBy('created_at', 'desc')

            ->paginate(10);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    // Crée une commande à partir du panier de l'utilisateur
    {
        $validated = $request->validate([
            'address' => 'required|string|max:500',
            // Adresse de livraison obligatoire
        ]);

        $cartItems = $request->user()
            ->cartItems()
            ->with('product')
            ->get();
        // Récupère tous les articles du panier de l'user connecté
        // avec leurs produits associés

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Votre panier est vide',
            ], 422);
            // On ne peut pas commander avec un panier vide
        }

        // Vérification du stock pour chaque article
        foreach ($cartItems as $item) {
            // foreach → boucle sur chaque article du panier
            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'message' => 'Stock insuffisant pour le produit : '
                        . $item->product->name
                        . '. Stock disponible : '
                        . $item->product->stock,
                ], 422);
                // Si un seul produit n'a pas assez de stock
                // → on arrête tout et on retourne une erreur
            }
        }

        // Calcul du total de la commande
        $total = $cartItems->sum(function ($item) {
            return $item->quantity * $item->product->price;
            // Prix au moment de l'achat
        });

        // DB::transaction() → groupe plusieurs opérations SQL
        // Si UNE opération échoue → TOUTES sont annulées (rollback)
        // Si TOUTES réussissent → TOUTES sont validées (commit)
        //
        // Sans transaction :
        // 1. Order créé ✅
        // 2. OrderItems créés ✅
        // 3. Stock mis à jour ❌ (erreur !)
        // → Commande créée mais stock pas mis à jour = incohérence !
        //
        // Avec transaction :
        // Si l'étape 3 échoue → étapes 1 et 2 sont annulées aussi
        // → La DB reste cohérente

        $order = DB::transaction(function () use ($cartItems, $total, $validated, $request) {
            // use() → injecte les variables extérieures dans la closure
            // Sans use(), $cartItems, $total, etc. ne seraient pas accessibles
            // à l'intérieur de la fonction anonyme

            // Étape 1 : Créer la commande
            $order = Order::create([
                'user_id' => $request->user()->id,
                'total'   => $total,
                'status'  => 'pending',
                'address' => $validated['address'],
            ]);

            // Étape 2 : Créer les articles de la commande
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'price'      => $item->product->price,
                    // On sauvegarde le prix ACTUEL du produit
                    // Car le prix peut changer demain
                ]);

                // Étape 3 : Décrémenter le stock du produit
                $item->product->decrement('stock', $item->quantity);
                // decrement('stock', $quantity)
                // → UPDATE products SET stock = stock - $quantity WHERE id = ?
                // Opération atomique → thread-safe
                // Pas de risque de race condition
            }

            // Étape 4 : Vider le panier de l'utilisateur
            $request->user()->cartItems()->delete();
            // DELETE FROM cart_items WHERE user_id = ?

            return $order;
            // On retourne la commande créée
            // Elle sera disponible dans $order après DB::transaction()
        });

        $order->load('items.product');
        // Charge les relations pour la réponse JSON

        return response()->json([
            'message' => 'Commande créée avec succès',
            'data'    => new OrderResource($order),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    // Affiche le détail d'une commande
    {
        if ($order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Accès non autorisé',
            ], 403);
            // Un user ne peut voir QUE ses propres commandes
            // Sauf l'admin qui peut tout voir
            //
            // $order->user_id !== $request->user()->id
            // → la commande n'appartient pas à l'user connecté
            //
            // && !$request->user()->isAdmin()
            // → ET l'user n'est pas admin
            //
            // Les deux conditions doivent être vraies pour refuser l'accès
        }

        $order->load('items.product', 'user');
        // Charge les articles, leurs produits, et l'utilisateur

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    // Vue admin : liste TOUTES les commandes (tous les users)
    {
        $orders = Order::query()
            ->with('user', 'items.product')
            // Charge l'utilisateur de chaque commande
            // pour afficher son nom dans la liste admin

            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            // Filtre par statut
            // GET /api/admin/orders?status=pending

            ->when($request->user_id, function ($query, $userId) {
                $query->where('user_id', $userId);
            })
            // Filtre par utilisateur
            // GET /api/admin/orders?user_id=5

            ->latest()
            ->paginate(20);
        // 20 commandes par page pour l'admin

        return response()->json([
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    // Met à jour le statut d'une commande (admin uniquement)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,shipped,delivered,cancelled',
            // in: → la valeur doit être dans cette liste
            // Protège contre les statuts invalides
        ]);

        // Vérification de la logique de transition des statuts
        $allowedTransitions = [
            'pending'   => ['paid', 'cancelled'],
            // pending  → peut passer à paid ou cancelled
            'paid'      => ['shipped', 'cancelled'],
            // paid     → peut passer à shipped ou cancelled
            'shipped'   => ['delivered'],
            // shipped  → peut seulement passer à delivered
            'delivered' => [],
            // delivered → état final, aucune transition possible
            'cancelled' => [],
            // cancelled → état final, aucune transition possible
        ];

        $currentStatus = $order->status;
        $newStatus     = $validated['status'];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
            return response()->json([
                'message' => 'Transition de statut invalide : '
                    . $currentStatus . ' → ' . $newStatus,
            ], 422);
            // On ne peut pas passer de 'delivered' à 'pending' par exemple
            // Les transitions doivent suivre le cycle de vie logique
        }

        $order->update(['status' => $newStatus]);

        return response()->json([
            'message' => 'Statut mis à jour : ' . $newStatus,
            'data'    => new OrderResource($order),
        ]);
    }
}
