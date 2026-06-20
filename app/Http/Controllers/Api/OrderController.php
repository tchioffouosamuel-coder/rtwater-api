<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * POST /api/admin/pos-order
     * Enregistre une vente caisse (POS) en mode admin. Crée l'ordre directement en statut "paid".
     */
    public function posStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'             => 'required|array|min:1',
            'items.*.product_id'=> 'required|integer|exists:products,id',
            'items.*.quantity'  => 'required|integer|min:1',
        ]);

        $lines    = collect($validated['items']);
        $products = Product::whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');

        // Stock check
        foreach ($lines as $item) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                return response()->json(['message' => 'Produit introuvable.'], 422);
            }
            if ($product->stock < $item['quantity']) {
                return response()->json([
                    'message' => "Stock insuffisant pour « {$product->name} ». Disponible : {$product->stock}",
                ], 422);
            }
        }

        $total = $lines->sum(fn($item) => $item['quantity'] * $products->get($item['product_id'])->price);

        $order = DB::transaction(function () use ($request, $lines, $products, $total) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'total'   => $total,
                'status'  => 'paid',
                'address' => 'Vente en caisse — Yaoundé',
            ]);

            foreach ($lines as $item) {
                $product = $products->get($item['product_id']);
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'price'      => $product->price,
                ]);
                $product->decrement('stock', $item['quantity']);
            }

            return $order;
        });

        $order->load('items.product', 'user');

        return response()->json([
            'message' => 'Vente enregistrée',
            'data'    => new OrderResource($order),
        ], 201);
    }

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
    // Crée une commande à partir du panier de l'utilisateur ou d'une commande express client
    {
        // Route publique → résoudre optionnellement l'utilisateur Sanctum sans middleware auth:sanctum
        $user = auth('sanctum')->user();

        $rules = [
            'address' => 'required|string|max:500',
        ];

        if ($user) {
            $rules['items'] = 'sometimes|array|min:1';
            $rules['items.*.product_id'] = 'required_with:items|integer|exists:products,id';
            $rules['items.*.quantity'] = 'required_with:items|integer|min:1';
        } else {
            $rules['name']  = 'required|string|max:255';
            $rules['email'] = 'nullable|string|email|max:255';
            $rules['phone'] = 'required|string|max:20';
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.product_id'] = 'required|integer|exists:products,id';
            $rules['items.*.quantity']   = 'required|integer|min:1';
        }

        $validated = $request->validate($rules);

        $orderLines = collect($validated['items'] ?? []);
        $cartItems = null;

        if ($orderLines->isEmpty()) {
            if (!$user) {
                return response()->json([
                    'message' => 'Les articles de la commande sont requis.',
                ], 422);
            }

            $cartItems = $user->cartItems()->with('product')->get();
            if ($cartItems->isEmpty()) {
                return response()->json([
                    'message' => 'Votre panier est vide',
                ], 422);
            }
        }

        if ($orderLines->isNotEmpty()) {
            $products = Product::whereIn('id', $orderLines->pluck('product_id'))->get()->keyBy('id');
            $orderLines = $orderLines->map(function ($item) use ($products) {
                return [
                    'product' => $products->get($item['product_id']),
                    'quantity' => $item['quantity'],
                ];
            });
        } else {
            $orderLines = $cartItems->map(function ($item) {
                return [
                    'product' => $item->product,
                    'quantity' => $item->quantity,
                    'cart_id' => $item->id,
                ];
            });
        }

        foreach ($orderLines as $line) {
            if (!$line['product']) {
                return response()->json([
                    'message' => 'Produit introuvable dans la commande.',
                ], 422);
            }

            if ($line['product']->stock < $line['quantity']) {
                return response()->json([
                    'message' => 'Stock insuffisant pour le produit : '
                        . $line['product']->name
                        . '. Stock disponible : '
                        . $line['product']->stock,
                ], 422);
            }
        }

        $client = $user;

        if (!$client && isset($validated['email'])) {
            $client = User::where('email', $validated['email'])->first();
        }

        if (!$client && isset($validated['phone'])) {
            $client = User::where('phone', $validated['phone'])->first();
        }

        if (!$client) {
            $client = User::create([
                'name'     => $validated['name'],
                'email'    => isset($validated['email'])
                    ? $validated['email']
                    : 'guest+' . Str::random(12) . '@example.com',
                'phone'    => $validated['phone'] ?? null,
                'address'  => $validated['address'],
                'password' => bcrypt(Str::random(16)),
            ]);
            $client->assignRole(Role::USER);
        } else {
            $updated = false;

            if (!$client->name && isset($validated['name'])) {
                $client->name = $validated['name'];
                $updated = true;
            }

            if (!$client->phone && isset($validated['phone'])) {
                $client->phone = $validated['phone'];
                $updated = true;
            }

            if (!$client->address && isset($validated['address'])) {
                $client->address = $validated['address'];
                $updated = true;
            }

            if ($updated) {
                $client->save();
            }
        }

        $total = $orderLines->sum(function ($line) {
            return $line['quantity'] * $line['product']->price;
        });

        $order = DB::transaction(function () use ($client, $orderLines, $total, $validated, $user) {
            $order = Order::create([
                'user_id' => $client->id,
                'total' => $total,
                'status' => 'pending',
                'address' => $validated['address'],
            ]);

            foreach ($orderLines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'price' => $line['product']->price,
                ]);

                $line['product']->decrement('stock', $line['quantity']);
            }

            if ($user && $orderLines->first()['cart_id'] ?? false) {
                $user->cartItems()->delete();
            }

            return $order;
        });

        $order->load('items.product', 'user');

        return response()->json([
            'message' => 'Commande créée avec succès',
            'data' => new OrderResource($order),
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

            ->when($request->from, fn($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->to,   fn($q, $to)   => $q->whereDate('created_at', '<=', $to))
            ->when($request->search, fn($q, $s)  => $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))

            ->latest()
            ->paginate($request->integer('per_page', 20));
        // 20 commandes par page pour l'admin (ou per_page si fourni)

        return response()->json([
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    public function downloadReceipt(Request $request, Order $order): \Illuminate\Http\Response
    {
        // Authorization: admin can download any, user only their own
        $currentUser = auth('sanctum')->user();
        if (!$currentUser) abort(401);
        if ($currentUser->id !== $order->user_id && !$currentUser->isAdmin()) abort(403);

        $order->load('items.product', 'user');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('receipts.order', ['order' => $order]);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("recu-commande-{$order->id}.pdf");
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
