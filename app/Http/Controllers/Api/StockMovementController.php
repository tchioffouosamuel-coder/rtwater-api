<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    // GET /api/admin/stock-movements — admin
    public function index(Request $request): JsonResponse
    {
        $movements = StockMovement::with('product:id,name,sku', 'user:id,name')
            ->when($request->product_id, fn($q, $id) => $q->where('product_id', $id))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->latest()
            ->paginate(50);

        return response()->json([
            'data'       => $movements->items(),
            'pagination' => [
                'current_page' => $movements->currentPage(),
                'last_page'    => $movements->lastPage(),
                'total'        => $movements->total(),
            ],
        ]);
    }

    // POST /api/admin/stock-movements — admin (ajustement manuel)
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type'       => 'required|in:in,out,adjustment',
            'quantity'   => 'required|integer|min:1',
            'reason'     => 'nullable|string|max:255',
            'notes'      => 'nullable|string',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $stockBefore = $product->stock;
        $delta       = $validated['type'] === 'out' ? -$validated['quantity'] : $validated['quantity'];
        $stockAfter  = max(0, $stockBefore + $delta);

        if ($validated['type'] === 'out' && $stockBefore < $validated['quantity']) {
            return response()->json([
                'message' => "Stock insuffisant. Stock actuel : {$stockBefore}",
            ], 422);
        }

        $movement = DB::transaction(function () use ($product, $validated, $stockBefore, $stockAfter, $request) {
            $product->update(['stock' => $stockAfter]);

            return StockMovement::create([
                'product_id'   => $product->id,
                'user_id'      => $request->user()?->id,
                'type'         => $validated['type'],
                'quantity'     => $validated['type'] === 'out' ? -$validated['quantity'] : $validated['quantity'],
                'stock_before' => $stockBefore,
                'stock_after'  => $stockAfter,
                'reason'       => $validated['reason'] ?? 'Ajustement manuel',
                'notes'        => $validated['notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => "Mouvement enregistré. Stock : {$stockBefore} → {$stockAfter}",
            'data'    => $movement->load('product:id,name,sku'),
        ], 201);
    }
}
