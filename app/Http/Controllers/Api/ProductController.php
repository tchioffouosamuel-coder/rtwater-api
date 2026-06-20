<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('category')
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->withCount('reviews')
            ->active()
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->search, fn($q, $v) => $q->where('name', 'like', "%$v%"))
            ->when($request->min_price, fn($q, $v) => $q->where('price', '>=', $v))
            ->when($request->max_price, fn($q, $v) => $q->where('price', '<=', $v))
            ->paginate(12);

        return response()->json([
            'data'       => ProductResource::collection($products),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    // StoreProductRequest → validation + autorisation automatiques
    {
        $validated = $request->validated();
        // validated() → retourne seulement les données validées
        // Plus sûr que $request->all() qui retourne TOUT

        if ($request->hasFile('image_url')) {
            $validated['image_url'] = $request->file('image_url')
                ->store('products', 'public');
            // Sauvegarde l'image et stocke le chemin dans $validated
            // ex: "products/AbCdEf123456.jpg"
        }

        $product = Product::create($validated);
        $product->load('category');

        return response()->json([
            'message' => 'Produit créé avec succès',
            'data'    => new ProductResource($product),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('category')
            ->loadAvg('reviews as reviews_avg_rating', 'rating')
            ->loadCount('reviews');

        return response()->json([
            'data' => new ProductResource($product),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image_url')) {
            // Supprime l'ancienne image si elle existe
            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
                // Supprime l'ancien fichier du storage
                // Évite d'accumuler des fichiers inutilisés
            }

            $validated['image_url'] = $request->file('image_url')
                ->store('products', 'public');
        }

        $product->update($validated);
        $product->load('category');

        return response()->json([
            'message' => 'Produit mis à jour avec succès',
            'data'    => new ProductResource($product),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->image_url) {
            Storage::disk('public')->delete($product->image_url);
            // Supprime l'image du storage quand on supprime le produit
            // Évite les fichiers orphelins qui prennent de la place
        }

        $product->delete();

        return response()->json([
            'message' => 'Produit supprimé avec succès',
        ]);
    }
}
