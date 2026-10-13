<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Setting;
use App\Support\DatasheetStorage;
use App\Support\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Le nombre de produits par page vient des reglages de la boutique,
        // la requete peut seulement le restreindre.
        $default = (int) Setting::get('products_per_page', 12);
        $perPage = min((int) ($request->per_page ?? $default), 48);

        $query = Product::query()
            ->with('category')
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->withCount('reviews')
            ->active()
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->search, function ($q, $term) {
                // La recherche couvre le nom, la reference et la description :
                // chercher « RTW-B15 » ou un mot de la fiche doit fonctionner.
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%$term%")
                        ->orWhere('sku', 'like', "%$term%")
                        ->orWhere('description', 'like', "%$term%");
                });
            })
            ->when($request->min_price, fn($q, $v) => $q->where('price', '>=', $v))
            ->when($request->max_price, fn($q, $v) => $q->where('price', '<=', $v))
            ->when($request->boolean('in_stock'), fn($q) => $q->where('stock', '>', 0))
            ->when($request->boolean('on_sale'), fn($q) => $q->onSale());

        match ($request->sort ?? 'newest') {
            'price-low'  => $query->orderBy('price', 'asc'),
            'price-high' => $query->orderBy('price', 'desc'),
            'rating'     => $query->orderByDesc('reviews_avg_rating'),
            'discount'   => $query->orderByDesc('discount'),
            'name'       => $query->orderBy('name'),
            default      => $query->latest(),
        };

        $products = $query->paginate($perPage);

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
            $validated['image_url'] = ImageOptimizer::store($request->file('image_url'), 'products');
            // L'attente d'image est levee : le produit n'a plus a figurer dans
            // la liste des photos a fournir.
            $validated['image_filename'] = null;
        }

        // Fiche technique : texte, image ou PDF selon datasheet_type
        $validated = array_merge($validated, DatasheetStorage::attributesFor(
            $validated,
            $request->file('datasheet_file'),
        ));
        unset($validated['datasheet_file']);

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

            $validated['image_url'] = ImageOptimizer::store($request->file('image_url'), 'products');
            $validated['image_filename'] = null;
        }

        $previousDatasheet = $product->datasheet_path;

        $validated = array_merge($validated, DatasheetStorage::attributesFor(
            $validated,
            $request->file('datasheet_file'),
            $product,
        ));
        unset($validated['datasheet_file']);

        $product->update($validated);

        // L'ancien fichier n'est supprime qu'une fois le produit enregistre :
        // si l'ecriture echoue, la fiche d'origine est toujours servie.
        DatasheetStorage::forget($previousDatasheet, $product->datasheet_path);

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

        DatasheetStorage::forget($product->datasheet_path);

        $product->delete();

        return response()->json([
            'message' => 'Produit supprimé avec succès',
        ]);
    }
}
