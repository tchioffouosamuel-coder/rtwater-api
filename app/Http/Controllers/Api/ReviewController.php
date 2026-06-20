<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // GET /products/{product}/reviews — public, avis approuvés uniquement
    public function index(Product $product): JsonResponse
    {
        $reviews = $product->reviews()
            ->approved()
            ->with('user')
            ->latest()
            ->paginate(10);

        $stats = [
            'count'      => $product->reviews()->approved()->count(),
            'avg_rating' => round((float) $product->reviews()->approved()->avg('rating'), 1),
            'breakdown'  => array_map(
                fn ($n) => $product->reviews()->approved()->where('rating', $n)->count(),
                [5, 4, 3, 2, 1]
            ),
        ];

        return response()->json([
            'data'       => ReviewResource::collection($reviews->items()),
            'stats'      => $stats,
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'total'        => $reviews->total(),
            ],
        ]);
    }

    // POST /products/{product}/reviews — auth requis
    public function store(StoreReviewRequest $request, Product $product): JsonResponse
    {
        // Un seul avis par produit par utilisateur
        if ($product->reviews()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Vous avez déjà laissé un avis pour ce produit.'], 422);
        }

        // Achat vérifié : commande payée/expédiée/livrée contenant ce produit
        $isVerified = Order::where('user_id', $request->user()->id)
            ->whereIn('status', ['paid', 'shipped', 'delivered'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        $review = $product->reviews()->create([
            'user_id'              => $request->user()->id,
            'rating'               => $request->rating,
            'title'                => $request->title,
            'content'              => $request->content,
            'status'               => 'pending',
            'is_verified_purchase' => $isVerified,
        ]);

        return response()->json([
            'message' => 'Votre avis a été soumis et est en attente de modération.',
            'data'    => new ReviewResource($review),
        ], 201);
    }

    // GET /admin/reviews — admin
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Review::with(['product', 'user'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $reviews = $query->paginate(20);

        $counts = [
            'total'    => Review::count(),
            'pending'  => Review::where('status', 'pending')->count(),
            'approved' => Review::where('status', 'approved')->count(),
            'rejected' => Review::where('status', 'rejected')->count(),
        ];

        return response()->json([
            'data'       => ReviewResource::collection($reviews->items()),
            'counts'     => $counts,
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'per_page'     => $reviews->perPage(),
                'total'        => $reviews->total(),
            ],
        ]);
    }

    // PUT /admin/reviews/{review} — admin : approve / reject
    public function adminUpdate(Request $request, Review $review): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $review->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Statut mis à jour.',
            'data'    => new ReviewResource($review->load(['product', 'user'])),
        ]);
    }

    // DELETE /admin/reviews/{review} — admin
    public function adminDestroy(Review $review): JsonResponse
    {
        $review->delete();

        return response()->json(['message' => 'Avis supprimé.']);
    }
}
