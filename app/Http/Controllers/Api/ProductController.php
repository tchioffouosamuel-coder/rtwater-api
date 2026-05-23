<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('category')
            // with('category') → Eager Loading
            // Charge la relation category en même temps
            // SANS with() : N+1 problème
            //   1 requête pour les produits
            //   + 1 requête par produit pour sa catégorie
            //   = 101 requêtes pour 100 produits !
            //
            // AVEC with() : 2 requêtes seulement
            //   1 pour les produits
            //   1 pour toutes leurs catégories
            //   Laravel fait la jointure en PHP

            ->active()
            // Appelle le scope qu'on a défini dans le Model Product
            // → where('is_active', true)

            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            // Filtre optionnel par catégorie
            // GET /api/products?category_id=2

            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', '%' . $search . '%');
                // LIKE '%iphone%' → cherche 'iphone' n'importe où dans le nom
                // % = wildcard (n'importe quels caractères)
            })
            // Recherche optionnelle par nom
            // GET /api/products?search=iphone

            ->when($request->min_price, function ($query, $minPrice) {
                $query->where('price', '>=', $minPrice);
            })
            ->when($request->max_price, function ($query, $maxPrice) {
                $query->where('price', '<=', $maxPrice);
            })
            // Filtres de prix optionnels
            // GET /api/products?min_price=100&max_price=500

            ->paginate(12);
        // paginate(12) → retourne 12 produits par page
        // Ajoute automatiquement les métadonnées de pagination :
        // current_page, last_page, total, per_page, next_page_url...
        // GET /api/products?page=2 → page suivante

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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            // exists:categories,id → vérifie que cette catégorie existe en DB
            // Si category_id=999 n'existe pas → erreur de validation

            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            // numeric → doit être un nombre (entier ou décimal)
            // min:0   → prix ne peut pas être négatif

            'stock'       => 'required|integer|min:0',
            // integer → nombre entier uniquement
            // min:0   → stock ne peut pas être négatif

            'image'       => 'nullable|image|max:2048',
            // image   → le fichier doit être une image (jpg, png, gif, webp...)
            // max:2048 → taille max 2 Mo (2048 Ko)

            'is_active'   => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')
                ->store('products', 'public');
            // hasFile('image') → vérifie si un fichier image a été envoyé
            //
            // ->store('products', 'public')
            // Sauvegarde le fichier dans storage/app/public/products/
            // Laravel génère automatiquement un nom unique pour le fichier
            // Retourne le chemin relatif : "products/abc123.jpg"
            // Ce chemin est stocké dans la colonne image de la DB
        }

        $product = Product::create($validated);
        $product->load('category');
        // load('category') → charge la relation après la création
        // Pour l'inclure dans la réponse JSON

        return response()->json([
            'message' => 'Produit créé avec succès',
            'data'    => new ProductResource($product),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('category');
        // load() = Lazy Eager Loading
        // Charge la relation sur un objet déjà récupéré

        return response()->json([
            'data' => new ProductResource($product),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'sometimes|numeric|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'image'       => 'nullable|image|max:2048',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')
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
        $product->delete();

        return response()->json([
            'message' => 'Produit supprimé avec succès',
        ]);
    }
}
