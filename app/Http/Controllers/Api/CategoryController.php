<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\CacheVersion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Cache 1 h, invalidé dès qu'une catégorie ou un produit change
        $data = CacheVersion::remember('categories', ['index', (string) $request->type], 3600,
            fn() => CategoryResource::collection($this->queryCategories($request))->resolve());

        return response()->json(['data' => $data]);
    }

    private function queryCategories(Request $request)
    {
        return Category::query()
            // query() → démarre une requête Eloquent
            // Équivalent à Category:: mais plus explicite
            // On peut chaîner des méthodes dessus

            ->withCount(['products', 'services'])
            // Ajoute un comptage en une seule requête SQL
            // Sans withCount : N+1 requêtes (une par catégorie)
            // Avec withCount : 1 seule requête avec COUNT()
            // Résultat : $category->products_count, $category->services_count

            ->when($request->type, function ($query, $type) {
                $query->where('type', $type);
            })
            // when($condition, $callback)
            // → exécute le callback SEULEMENT si $condition est vraie
            //
            // $request->type → la valeur du paramètre ?type= dans l'URL
            // GET /api/categories?type=product → filtre sur 'product'
            // GET /api/categories?type=service → filtre sur 'service'
            // GET /api/categories               → pas de filtre, tout retourner
            //
            // Sans when() tu devrais écrire :
            // if ($request->type) {
            //     $query->where('type', $request->type);
            // }

            ->get();
        // Exécute la requête et retourne une Collection
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Le slug est optionnel : derive du nom quand l'admin ne le saisit
            // pas, ce qui est le cas depuis le backoffice.
            'slug' => 'nullable|string|unique:categories,slug|max:255',
            'type' => 'required|in:product,service',
        ], [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'slug.unique'   => 'Une catégorie utilise déjà cette adresse.',
            'type.required' => "Précisez s'il s'agit d'une catégorie de produits ou de services.",
        ]);

        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? $validated['name']);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Catégorie créée avec succès',
            'data'    => new CategoryResource($category),
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->loadCount(['products', 'services']);
        // loadCount() → charge les comptages sur un objet déjà récupéré
        // Différence avec withCount() :
        // withCount() → dans la requête initiale (avant get())
        // loadCount() → après avoir récupéré l'objet (lazy loading)

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:categories,slug,' . $category->id,
            // unique:categories,slug,{id}
            // → slug doit être unique SAUF pour cet enregistrement lui-même
            // Sinon, modifier une catégorie sans changer son slug
            // déclencherait une erreur "slug déjà pris" (par lui-même !)
            'type' => 'sometimes|in:product,service',
        ]);

        if (isset($validated['slug'])) {
            $validated['slug'] = $this->uniqueSlug($validated['slug'], $category->id);
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Catégorie mise à jour avec succès',
            'data'    => new CategoryResource($category),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        // Supprimer une categorie supprimerait ses produits en cascade :
        // on refuse tant qu'elle est utilisee, et on dit par quoi.
        $products = $category->products()->count();
        $services = $category->services()->count();

        if ($products > 0 || $services > 0) {
            $parts = [];
            if ($products > 0) {
                $parts[] = "$products produit" . ($products > 1 ? 's' : '');
            }
            if ($services > 0) {
                $parts[] = "$services service" . ($services > 1 ? 's' : '');
            }

            return response()->json([
                'message' => 'Cette catégorie contient ' . implode(' et ', $parts)
                    . '. Déplacez-les avant de la supprimer.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Catégorie supprimée avec succès',
        ]);
    }

    /** Slug lisible et unique, derive du nom si besoin. */
    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($source) ?: \Illuminate\Support\Str::random(8);
        $slug = $base;

        for ($i = 2; $i < 100; $i++) {
            $exists = Category::where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->whereNot('id', $ignoreId))
                ->exists();

            if (!$exists) {
                return $slug;
            }
            $slug = "$base-$i";
        }

        return $base . '-' . \Illuminate\Support\Str::random(5);
    }
}
