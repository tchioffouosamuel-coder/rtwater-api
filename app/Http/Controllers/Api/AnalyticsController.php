<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * GET /api/admin/analytics?period=week|month|year
     */
    public function index(Request $request): JsonResponse
    {
        $period = $request->query('period', 'week');

        [$startCurrent, $startPrevious, $groupFn, $labelFn] = match ($period) {
            'month' => [
                now()->subDays(29)->startOfDay(),
                now()->subDays(59)->startOfDay(),
                'DATE(created_at)',
                fn($d) => \Carbon\Carbon::parse($d)->format('d/m'),
            ],
            'year' => [
                now()->subMonths(11)->startOfMonth(),
                now()->subMonths(23)->startOfMonth(),
                "DATE_FORMAT(created_at, '%Y-%m')",
                fn($d) => \Carbon\Carbon::parse($d . '-01')->format('M y'),
            ],
            default => [ // week
                now()->subDays(6)->startOfDay(),
                now()->subDays(13)->startOfDay(),
                'DATE(created_at)',
                fn($d) => match (\Carbon\Carbon::parse($d)->dayOfWeek) {
                    0 => 'Dim', 1 => 'Lun', 2 => 'Mar', 3 => 'Mer',
                    4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', default => $d,
                },
            ],
        };

        $endCurrent  = now();
        $endPrevious = $startCurrent->copy()->subSecond();

        // ── Revenue trend ────────────────────────────────────────────────
        $trendRows = Order::query()
            ->whereNotIn('status', ['cancelled'])
            ->where('created_at', '>=', $startCurrent)
            ->selectRaw("{$groupFn} as period_key, SUM(total) as revenue, COUNT(*) as orders_count")
            ->groupByRaw($groupFn)
            ->orderByRaw($groupFn)
            ->get();

        $revenueTrend = $trendRows->map(fn($row) => [
            'date'    => $labelFn($row->period_key),
            'revenue' => round((float) $row->revenue, 2),
            'orders'  => (int) $row->orders_count,
        ])->values()->toArray();

        // ── KPIs — current period ────────────────────────────────────────
        $currentOrders = Order::whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [$startCurrent, $endCurrent]);

        $totalRevenue  = (float) (clone $currentOrders)->sum('total');
        $totalOrders   = (clone $currentOrders)->count();
        $todayRevenue  = (float) Order::whereNotIn('status', ['cancelled'])
            ->whereDate('created_at', today())->sum('total');

        // KPIs — previous period (for % change)
        $previousOrders = Order::whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [$startPrevious, $endPrevious]);

        $prevRevenue = (float) (clone $previousOrders)->sum('total');
        $prevCount   = (clone $previousOrders)->count();

        $revenueChange = $prevRevenue > 0
            ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : null;
        $ordersChange = $prevCount > 0
            ? round((($totalOrders - $prevCount) / $prevCount) * 100, 1)
            : null;

        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        // Customers unique (users who ordered in period)
        $totalCustomers = Order::whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [$startCurrent, $endCurrent])
            ->distinct('user_id')
            ->count('user_id');

        // Stock alerts
        $lowStockCount      = Product::where('stock', '>', 0)
            ->whereColumn('stock', '<=', DB::raw('min_quantity'))
            ->count();
        $criticalStockCount = Product::where('stock', 0)->count();
        $productsCount      = Product::where('is_available', true)->count();

        // ── Top products ─────────────────────────────────────────────────
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereNotIn('orders.status', ['cancelled'])
            ->where('orders.created_at', '>=', $startCurrent)
            ->selectRaw('products.name, SUM(order_items.quantity) as total_sales, SUM(order_items.quantity * order_items.price) as total_revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'name'    => $r->name,
                'sales'   => (int) $r->total_sales,
                'revenue' => round((float) $r->total_revenue, 2),
            ])
            ->values()
            ->toArray();

        // ── Payment methods ──────────────────────────────────────────────
        $paymentRows = Payment::where('status', 'success')
            ->where('created_at', '>=', $startCurrent)
            ->selectRaw('operator, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('operator')
            ->get();

        $paymentTotal = $paymentRows->sum('cnt') ?: 1;
        $COLORS = ['#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#ec4899'];
        $paymentMethods = $paymentRows->values()->map(fn($r, $i) => [
            'name'  => $r->operator,
            'value' => round(($r->cnt / $paymentTotal) * 100, 1),
            'color' => $COLORS[$i % count($COLORS)],
        ])->toArray();

        // Fallback if no payments yet
        if (empty($paymentMethods)) {
            $paymentMethods = [
                ['name' => 'Orange Money', 'value' => 0, 'color' => '#FF6200'],
                ['name' => 'MTN Money',    'value' => 0, 'color' => '#FFCC00'],
                ['name' => 'Moov Money',   'value' => 0, 'color' => '#0066CC'],
                ['name' => 'Wave Money',   'value' => 0, 'color' => '#1AC8ED'],
            ];
        }

        // ── Recent orders (activity feed) ────────────────────────────────
        $recentOrders = Order::with('user')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($o) => [
                'id'         => $o->id,
                'customer'   => $o->user?->name ?? 'Client invité',
                'total'      => (float) $o->total,
                'status'     => $o->status,
                'created_at' => $o->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'kpis' => [
                'total_revenue'        => $totalRevenue,
                'total_orders'         => $totalOrders,
                'avg_order_value'      => $avgOrderValue,
                'today_revenue'        => $todayRevenue,
                'total_customers'      => $totalCustomers,
                'products_count'       => $productsCount,
                'revenue_change_pct'   => $revenueChange,
                'orders_change_pct'    => $ordersChange,
                'low_stock_count'      => $lowStockCount,
                'critical_stock_count' => $criticalStockCount,
            ],
            'revenue_trend'   => $revenueTrend,
            'top_products'    => $topProducts,
            'payment_methods' => $paymentMethods,
            'recent_orders'   => $recentOrders,
        ]);
    }
}
