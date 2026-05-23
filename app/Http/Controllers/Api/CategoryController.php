<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
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

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:categories,slug|max:255',
            'type' => 'required|in:product,service',
            // in:product,service → la valeur doit être
            // exactement 'product' ou 'service'
            // Toute autre valeur → erreur de validation
        ]);

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

        $category->update($validated);

        return response()->json([
            'message' => 'Catégorie mise à jour avec succès',
            'data'    => new CategoryResource($category),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'message' => 'Catégorie supprimée avec succès',
        ]);
    }
}
