<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    // index() → retourne la liste de tous les rôles
    // : JsonResponse → cette méthode retourne obligatoirement du JSON
    {
        $roles = Role::all();
        // Role::all() → SELECT * FROM roles
        // Retourne une Collection Eloquent (liste d'objets Role)

        return response()->json([
            'data' => RoleResource::collection($roles),
            // RoleResource::collection() → formate chaque Role
            // avec notre Resource personnalisée
        ]);
    }

    public function store(Request $request): JsonResponse
    // store() → crée un nouveau rôle (action admin uniquement)
    {
        $validated = $request->validate([
            'name'        => 'required|string|unique:roles,name|max:50',
            // required  → champ obligatoire
            // string    → doit être une chaîne de caractères
            // unique:roles,name → doit être unique dans la table roles colonne name
            // max:50    → maximum 50 caractères

            'label'       => 'required|string|max:100',
            // required + string + max 100 caractères

            'description' => 'nullable|string|max:255',
            // nullable → optionnel, peut être absent ou null
        ]);
        // validate() → si une règle échoue, Laravel retourne
        // automatiquement une réponse 422 avec les erreurs :
        // { "message": "...", "errors": { "name": ["Le champ name est requis"] } }
        // Le code après validate() ne s'exécute PAS si la validation échoue

        $role = Role::create($validated);
        // $validated est le tableau des données validées et nettoyées
        // Role::create() → INSERT INTO roles (...) VALUES (...)
        // Retourne l'objet Role créé avec son id

        return response()->json([
            'message' => 'Rôle créé avec succès',
            'data'    => new RoleResource($role),
        ], 201);
        // 201 = HTTP Created → la ressource a été créée avec succès
        // Par défaut response()->json() retourne 200
        // On précise 201 pour respecter les standards REST
    }

    public function show(Role $role): JsonResponse
    // show() → retourne un seul rôle
    // Role $role → "Route Model Binding"
    // Laravel injecte automatiquement l'objet Role
    // correspondant à l'id dans l'URL
    //
    // GET /api/roles/3 → Laravel fait Role::findOrFail(3)
    // et injecte l'objet dans $role
    // Si id=999 n'existe pas → Laravel retourne automatiquement 404
    {
        return response()->json([
            'data' => new RoleResource($role),
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->isPredefined()) {
            return response()->json([
                'message' => 'Les rôles prédéfinis ne peuvent pas être modifiés',
            ], 403);
            // 403 = Forbidden → l'action est interdite
            // On protège les rôles système contre la modification
        }

        $validated = $request->validate([
            'label'       => 'sometimes|string|max:100',
            // sometimes → valide ce champ SEULEMENT s'il est présent
            // Utile pour les mises à jour partielles (PATCH)
            // Si 'label' n'est pas envoyé → pas de validation, pas de modification

            'description' => 'nullable|string|max:255',
        ]);

        $role->update($validated);
        // UPDATE roles SET label = '...', description = '...' WHERE id = ?
        // Eloquent met à jour automatiquement updated_at

        return response()->json([
            'message' => 'Rôle mis à jour avec succès',
            'data'    => new RoleResource($role),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->isPredefined()) {
            return response()->json([
                'message' => 'Les rôles prédéfinis ne peuvent pas être supprimés',
            ], 403);
            // Protection des rôles système
        }

        $role->delete();
        // DELETE FROM roles WHERE id = ?
        // Grâce à onDelete('cascade') dans role_user :
        // → Les attributions de ce rôle dans role_user sont supprimées aussi

        return response()->json([
            'message' => 'Rôle supprimé avec succès',
        ]);
    }
}
