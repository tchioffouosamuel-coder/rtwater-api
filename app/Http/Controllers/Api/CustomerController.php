<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fiches clients du backoffice.
 *
 * Un « client » est un utilisateur sans rôle d'équipe : les comptes admin et
 * gestionnaire ne doivent pas polluer la liste commerciale.
 */
class CustomerController extends Controller
{
    /** Rôles qui désignent l'équipe, donc exclus de la clientèle. */
    private const STAFF_ROLES = [Role::ADMIN, Role::GESTIONNAIRE, Role::SECRETAIRE, Role::COMPTABLE];

    /** GET /api/admin/customers */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->per_page ?? 20), 100);

        $customers = $this->baseQuery()
            ->when($request->search, function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%$term%")
                        ->orWhere('email', 'like', "%$term%")
                        ->orWhere('phone', 'like', "%$term%");
                });
            })
            ->when($request->segment === 'with_orders', fn($q) => $q->whereHas(
                'orders', fn($o) => $o->whereNot('status', 'cancelled')
            ))
            ->when($request->segment === 'without_orders', fn($q) => $q->whereDoesntHave(
                'orders', fn($o) => $o->whereNot('status', 'cancelled')
            ))
            ->when($request->segment === 'guests', fn($q) => $q->where('email', 'like', 'guest+%'))
            ->orderByDesc($this->sortColumn($request->sort))
            ->paginate($perPage);

        return response()->json([
            'data'       => $customers->getCollection()->map(fn($u) => $this->present($u))->all(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
            ],
            'stats'      => $this->stats(),
        ]);
    }

    /** GET /api/admin/customers/{user} — fiche détaillée avec historique. */
    public function show(User $user): JsonResponse
    {
        if ($user->hasAnyRole(self::STAFF_ROLES)) {
            return response()->json(['message' => "Ce compte n'est pas un client."], 404);
        }

        $orders = Order::where('user_id', $user->id)
            ->with('items.product:id,name')
            ->latest()
            ->limit(50)
            ->get();

        $paid = $orders->whereNotIn('status', ['cancelled']);

        return response()->json([
            'data' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $this->visibleEmail($user),
                'phone'         => $user->phone,
                'address'       => $user->address,
                'is_guest'      => $this->isGuest($user),
                'created_at'    => $user->created_at?->toIso8601String(),

                'orders_count'  => $paid->count(),
                'total_spent'   => round((float) $paid->sum('total'), 2),
                'average_cart'  => $paid->count() ? round((float) $paid->sum('total') / $paid->count(), 2) : 0.0,
                'last_order_at' => $orders->first()?->created_at?->toIso8601String(),

                'orders' => $orders->map(fn($o) => [
                    'id'         => $o->id,
                    'reference'  => $o->reference,
                    'status'     => $o->status,
                    'total'      => (float) $o->total,
                    'created_at' => $o->created_at?->toIso8601String(),
                    'items'      => $o->items->map(fn($i) => [
                        'name'     => $i->product->name ?? 'Article supprimé',
                        'quantity' => $i->quantity,
                        'price'    => (float) $i->price,
                    ]),
                ]),
            ],
        ]);
    }

    /** GET /api/admin/customers/export — CSV, ouvrable dans Excel. */
    public function export(): StreamedResponse
    {
        $filename = 'clients-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 : sans lui Excel sous Windows casse les accents
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Nom', 'E-mail', 'Téléphone', 'Adresse', 'Commandes',
                'Total dépensé (XAF)', 'Panier moyen (XAF)', 'Dernière commande', 'Inscrit le',
            ], ';');

            $this->baseQuery()->orderBy('users.id')->chunk(500, function ($customers) use ($out) {
                foreach ($customers as $customer) {
                    fputcsv($out, [
                        $customer->name,
                        $this->visibleEmail($customer),
                        $customer->phone,
                        $customer->address,
                        (int) $customer->orders_count,
                        round((float) $customer->total_spent, 2),
                        $customer->orders_count ? round((float) $customer->total_spent / $customer->orders_count, 2) : 0,
                        $customer->last_order_at ? date('d/m/Y', strtotime($customer->last_order_at)) : '',
                        $customer->created_at?->format('d/m/Y'),
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    // ═══════════════════════════════════════════════════

    /**
     * Base commune à la liste et à l'export : agrégats de commandes calculés en
     * SQL plutôt que ligne par ligne, pour que l'export de 5 000 clients tienne.
     */
    private function baseQuery()
    {
        return User::query()
            ->whereDoesntHave('roles', fn($q) => $q->whereIn('name', self::STAFF_ROLES))
            ->select('users.*')
            ->selectSub(
                Order::selectRaw('COUNT(*)')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->whereNot('status', 'cancelled'),
                'orders_count'
            )
            ->selectSub(
                Order::selectRaw('COALESCE(SUM(total), 0)')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->whereNot('status', 'cancelled'),
                'total_spent'
            )
            ->selectSub(
                Order::selectRaw('MAX(created_at)')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->whereNot('status', 'cancelled'),
                'last_order_at'
            );
    }

    private function sortColumn(?string $sort): string
    {
        return match ($sort) {
            'spent'  => 'total_spent',
            'orders' => 'orders_count',
            'recent' => 'last_order_at',
            default  => 'users.created_at',
        };
    }

    /** @return array<string, int|float> */
    private function stats(): array
    {
        $base = User::query()->whereDoesntHave('roles', fn($q) => $q->whereIn('name', self::STAFF_ROLES));

        $valid   = Order::whereNot('status', 'cancelled');
        $revenue = (float) (clone $valid)->sum('total');
        $count   = (int) (clone $valid)->count();
        $buyers  = (int) (clone $valid)->distinct()->count('user_id');

        return [
            'total'        => (clone $base)->count(),
            'new_30d'      => (clone $base)->where('created_at', '>=', now()->subDays(30))->count(),
            'buyers'       => $buyers,
            'revenue'      => round($revenue, 2),
            // Panier moyen par commande, et non par client : c'est l'indicateur usuel
            'average_cart' => $count > 0 ? round($revenue / $count, 2) : 0.0,
        ];
    }

    /** Un compte invité porte une adresse technique : inutile de l'afficher. */
    private function isGuest(User $user): bool
    {
        return str_starts_with((string) $user->email, 'guest+');
    }

    private function visibleEmail(User $user): ?string
    {
        return $this->isGuest($user) ? null : $user->email;
    }

    private function present(User $user): array
    {
        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $this->visibleEmail($user),
            'phone'         => $user->phone,
            'address'       => $user->address,
            'is_guest'      => $this->isGuest($user),
            'orders_count'  => (int) $user->orders_count,
            'total_spent'   => round((float) $user->total_spent, 2),
            'last_order_at' => $user->last_order_at,
            'created_at'    => $user->created_at?->toIso8601String(),
        ];
    }
}
