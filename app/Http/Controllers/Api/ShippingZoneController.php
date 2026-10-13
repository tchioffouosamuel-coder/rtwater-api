<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Zones de livraison et leurs tarifs. */
class ShippingZoneController extends Controller
{
    /** GET /api/shipping-zones — public, utilisé au moment de commander. */
    public function publicIndex(Request $request): JsonResponse
    {
        $subtotal = (float) $request->query('subtotal', 0);

        $zones = ShippingZone::active()->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'data' => $zones->map(fn($z) => [
                'id'          => $z->id,
                'name'        => $z->name,
                'description' => $z->description,
                'fee'         => (float) $z->fee,
                'free_above'  => $z->free_above !== null ? (float) $z->free_above : null,
                'delay'       => $z->delay,
                // Tarif réellement dû pour ce panier : 0 si le franco de port est atteint
                'fee_for_cart' => $z->feeFor($subtotal),
            ])->all(),
        ]);
    }

    /** GET /api/admin/shipping-zones */
    public function index(): JsonResponse
    {
        $zones = ShippingZone::withCount('orders')->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['data' => $zones->map(fn($z) => $this->present($z))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        // refresh() : recupere is_active et sort_order poses par la base
        $zone = ShippingZone::create($this->validated($request))->refresh();

        return response()->json([
            'message' => 'Zone de livraison créée',
            'data'    => $this->present($zone),
        ], 201);
    }

    public function update(Request $request, ShippingZone $shippingZone): JsonResponse
    {
        $shippingZone->update($this->validated($request, true));

        return response()->json([
            'message' => 'Zone de livraison mise à jour',
            'data'    => $this->present($shippingZone->fresh()),
        ]);
    }

    public function destroy(ShippingZone $shippingZone): JsonResponse
    {
        // Des commandes pointent sur la zone : la désactiver préserve l'historique
        if ($shippingZone->orders()->exists()) {
            $shippingZone->update(['is_active' => false]);

            return response()->json([
                'message' => 'Des commandes utilisent cette zone : elle a été désactivée plutôt que supprimée.',
                'data'    => $this->present($shippingZone->fresh()),
            ]);
        }

        $shippingZone->delete();

        return response()->json(['message' => 'Zone supprimée']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name'        => [$required, 'string', 'max:120'],
            'description' => 'nullable|string|max:255',
            'fee'         => [$required, 'numeric', 'min:0', 'max:9999999'],
            'free_above'  => 'nullable|numeric|min:0|max:99999999',
            'delay'       => 'nullable|string|max:60',
            'is_active'   => 'boolean',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
        ], [
            'name.required' => 'Le nom de la zone est obligatoire.',
            'fee.required'  => 'Le tarif de livraison est obligatoire (0 pour une livraison offerte).',
        ]);
    }

    private function present(ShippingZone $z): array
    {
        return [
            'id'           => $z->id,
            'name'         => $z->name,
            'description'  => $z->description,
            'fee'          => (float) $z->fee,
            'free_above'   => $z->free_above !== null ? (float) $z->free_above : null,
            'delay'        => $z->delay,
            'is_active'    => $z->is_active,
            'sort_order'   => $z->sort_order,
            'orders_count' => (int) ($z->orders_count ?? 0),
        ];
    }
}
