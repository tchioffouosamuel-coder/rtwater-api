<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShippingZone;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Support\Notifier;
use App\Support\OrderPricing;
use App\Support\StockReserver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
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

        if ($lines->contains(fn($item) => !$products->get($item['product_id']))) {
            return response()->json(['message' => 'Produit introuvable.'], 422);
        }

        $orderLines = $lines->map(fn($item) => [
            'product'  => $products->get($item['product_id']),
            'quantity' => (int) $item['quantity'],
        ]);

        // En caisse : pas de livraison ni de code promo, mais les remises
        // produit s'appliquent comme en ligne.
        $quote = OrderPricing::quote($orderLines);

        try {
            $order = DB::transaction(function () use ($request, $orderLines, $quote) {
                // Le stock est relu sous verrou ICI, pas avant la transaction :
                // sinon deux ventes simultanees sur le dernier article passent
                // toutes les deux la verification.
                StockReserver::reserve($orderLines);

                $order = Order::create([
                    'user_id'        => $request->user()->id,
                    'subtotal'       => $quote['subtotal'],
                    'total'          => $quote['total'],
                    'status'         => 'paid',
                    'payment_method' => 'cash',
                    'address'        => 'Vente en caisse',
                ]);

                foreach ($orderLines as $line) {
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $line['product']->id,
                        'quantity'   => $line['quantity'],
                        'price'      => OrderPricing::unitPrice($line['product']),
                    ]);
                }

                return $order;
            });
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

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
            'address'          => 'required|string|max:500',
            'shipping_zone_id' => 'nullable|integer|exists:shipping_zones,id',
            'discount_code'    => 'nullable|string|max:50',
            'payment_method'   => 'nullable|in:mobile_money,cash',
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

        if ($orderLines->contains(fn($line) => !$line['product'])) {
            return response()->json([
                'message' => 'Produit introuvable dans la commande.',
            ], 422);
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

        $zone = isset($validated['shipping_zone_id'])
            ? ShippingZone::active()->find($validated['shipping_zone_id'])
            : null;

        // Les montants sont recalcules ici a partir de la base : ceux envoyes par
        // le navigateur ne sont jamais repris, ils sont modifiables par le client.
        $quote = OrderPricing::quote($orderLines, $zone, $validated['discount_code'] ?? null, $client);

        if ($quote['discount_error'] !== null) {
            return response()->json([
                'message' => $quote['discount_error'],
                'errors'  => ['discount_code' => [$quote['discount_error']]],
            ], 422);
        }

        try {
            $order = DB::transaction(function () use ($client, $orderLines, $quote, $zone, $validated, $user) {
                // Stock relu sous verrou dans la transaction : seul moyen d'empecher
                // deux acheteurs simultanes de prendre le meme dernier article.
                StockReserver::reserve($orderLines);

                $order = Order::create([
                    'user_id'          => $client->id,
                    'subtotal'         => $quote['subtotal'],
                    'shipping_fee'     => $quote['shipping_fee'],
                    'discount_total'   => $quote['discount_total'],
                    'discount_code'    => $quote['discount_code'],
                    'shipping_zone_id' => $zone?->id,
                    'total'            => $quote['total'],
                    'status'           => 'pending',
                    'payment_method'   => $validated['payment_method'] ?? null,
                    'address'          => $validated['address'],
                ]);

                foreach ($orderLines as $line) {
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $line['product']->id,
                        'quantity'   => $line['quantity'],
                        'price'      => OrderPricing::unitPrice($line['product']),
                    ]);
                }

                // Compteur d'utilisations du code promo, sous le meme verrou que
                // la commande : c'est lui qui fait respecter max_uses.
                if ($quote['discount_code'] !== null) {
                    Discount::where('code', $quote['discount_code'])->lockForUpdate()->first()?->increment('used_count');
                }

                if ($user && ($orderLines->first()['cart_id'] ?? false)) {
                    $user->cartItems()->delete();
                }

                return $order;
            });
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load('items.product', 'user');

        Notifier::toUser($order->user, new OrderPlacedNotification($order));
        Notifier::toAdmin(new AdminAlertNotification(
            "Nouvelle commande {$order->reference}",
            [
                'Client : ' . $order->user->name . ($order->user->phone ? ' · ' . $order->user->phone : ''),
                'Montant : ' . Notifier::money($order->total),
                'Livraison : ' . $order->address,
            ],
            Notifier::frontendUrl('admin/orders'),
        ));

        return response()->json([
            'message' => 'Commande créée avec succès',
            'data' => new OrderResource($order),
            // Lien signé (24 h) : permet à un invité sans compte de suivre le paiement
            'status_url' => URL::temporarySignedRoute('orders.status', now()->addDay(), ['order' => $order->id]),
        ], 201);
    }

    /**
     * POST /api/orders/quote — public.
     * Recalcule le detail d'un panier (remises produit, livraison, code promo)
     * pour l'afficher avant validation. Ne cree rien et n'engage rien.
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'              => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1|max:1000',
            'shipping_zone_id'   => 'nullable|integer|exists:shipping_zones,id',
            'discount_code'      => 'nullable|string|max:50',
        ]);

        $products = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $lines = collect($validated['items'])
            ->filter(fn($item) => $products->has($item['product_id']))
            ->map(fn($item) => [
                'product'  => $products->get($item['product_id']),
                'quantity' => (int) $item['quantity'],
            ]);

        if ($lines->isEmpty()) {
            return response()->json(['message' => 'Aucun produit valide dans le panier.'], 422);
        }

        $zone = isset($validated['shipping_zone_id'])
            ? ShippingZone::active()->find($validated['shipping_zone_id'])
            : null;

        $quote = OrderPricing::quote($lines, $zone, $validated['discount_code'] ?? null, auth('sanctum')->user());

        return response()->json([
            'data' => [
                'subtotal'       => $quote['subtotal'],
                'shipping_fee'   => $quote['shipping_fee'],
                'discount_total' => $quote['discount_total'],
                'discount_code'  => $quote['discount_code'],
                'total'          => $quote['total'],
                // Le code refuse n'est pas une erreur de requete : le panier reste
                // valide, on affiche simplement pourquoi le code ne s'applique pas.
                'discount_error' => $quote['discount_error'],
                'shipping_zone'  => $zone ? ['id' => $zone->id, 'name' => $zone->name] : null,
                'lines'          => $lines->map(fn($l) => [
                    'product_id' => $l['product']->id,
                    'name'       => $l['product']->name,
                    'quantity'   => $l['quantity'],
                    'unit_price' => OrderPricing::unitPrice($l['product']),
                    'base_price' => (float) $l['product']->price,
                    'discount'   => $l['product']->activeDiscount(),
                ])->values(),
            ],
        ]);
    }

    /**
     * GET /api/orders/{order}/status?expires=…&signature=…
     * Statut minimal d'une commande, via le lien signé renvoyé à la création.
     */
    public function status(Order $order): JsonResponse
    {
        return response()->json([
            'data' => [
                'id'        => $order->id,
                'reference' => $order->reference,
                'status'    => $order->status,
            ],
        ]);
    }

    /**
     * GET /api/orders/track?reference=CMD-000042&contact=6XXXXXXXX
     * Suivi public sans compte : la référence ET le téléphone (ou l'e-mail) doivent correspondre.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string|max:30',
            'contact'   => 'required|string|max:255',
        ]);

        $order   = Order::findByReference($validated['reference'])
            ?->load('items.product', 'user', 'payment');
        $contact = trim($validated['contact']);
        $digits  = fn(?string $v) => substr(preg_replace('/\D/', '', (string) $v), -8);

        $matches = $order && $order->user && (
            strcasecmp($order->user->email, $contact) === 0
            || ($order->user->phone && strlen($digits($contact)) === 8 && $digits($order->user->phone) === $digits($contact))
        );

        if (!$matches) {
            return response()->json([
                'message' => 'Aucune commande ne correspond à cette référence et ce contact.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'reference'      => $order->reference,
                'status'         => $order->status,
                'subtotal'       => (float) $order->subtotal,
                'shipping_fee'   => (float) $order->shipping_fee,
                'discount_total' => (float) $order->discount_total,
                'total'          => (float) $order->total,
                'address'        => $order->address,
                'created_at' => $order->created_at->format('d/m/Y à H:i'),
                'updated_at' => $order->updated_at->format('d/m/Y à H:i'),
                'payment'    => $order->payment ? [
                    'operator' => $order->payment->operator,
                    'status'   => $order->payment->status,
                ] : null,
                'items' => $order->items->map(fn($i) => [
                    'name'     => $i->product->name ?? 'Article',
                    'quantity' => $i->quantity,
                    'price'    => (float) $i->price,
                ]),
            ],
        ]);
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

        DB::transaction(function () use ($order, $newStatus) {
            // Une commande annulée libère son stock et rend son code promo
            if ($newStatus === 'cancelled') {
                foreach ($order->items()->get() as $item) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }

                if ($order->discount_code) {
                    Discount::where('code', $order->discount_code)
                        ->where('used_count', '>', 0)
                        ->decrement('used_count');
                }
            }

            $order->update(['status' => $newStatus]);
        });

        if (OrderStatusChangedNotification::supports($newStatus)) {
            Notifier::toUser($order->user, new OrderStatusChangedNotification($order));
        }

        return response()->json([
            'message' => 'Statut mis à jour : ' . $newStatus,
            'data'    => new OrderResource($order),
        ]);
    }
}
