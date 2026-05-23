<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    // Liste tous les services disponibles
    {
        $services = Service::query()
            ->with('category')
            // Charge la catégorie de chaque service en même temps
            // Évite le problème N+1 :
            // Sans with() → 1 requête pour les services
            //               + 1 requête PAR service pour sa catégorie
            // Avec with() → 2 requêtes en tout, peu importe le nombre de services

            ->available()
            // Appelle le scope défini dans le Model Service
            // → where('is_available', true)
            // Retourne seulement les services actifs

            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            // Filtre optionnel par catégorie
            // GET /api/services?category_id=3

            ->when($request->max_price, function ($query, $maxPrice) {
                $query->where('price', '<=', $maxPrice);
            })
            // Filtre optionnel par prix maximum
            // GET /api/services?max_price=100

            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            // Recherche par nom
            // GET /api/services?search=massage

            ->paginate(12);
        // 12 services par page

        return response()->json([
            'data' => ServiceResource::collection($services),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page'    => $services->lastPage(),
                'per_page'     => $services->perPage(),
                'total'        => $services->total(),
            ],
        ]);
    }

    public function show(Service $service): JsonResponse
    // Affiche le détail d'un service
    {
        $service->load('category');
        // Charge la catégorie sur l'objet déjà récupéré
        // par le Route Model Binding

        $service->loadCount('bookings');
        // Charge le nombre total de réservations pour ce service
        // $service->bookings_count → ex: 42

        return response()->json([
            'data' => new ServiceResource($service),
        ]);
    }

    public function store(Request $request): JsonResponse
    // Crée un nouveau service (admin uniquement)
    {
        $validated = $request->validate([
            'category_id'      => 'required|exists:categories,id',
            // exists:categories,id → vérifie que la catégorie existe en DB

            'name'             => 'required|string|max:255',

            'description'      => 'nullable|string',

            'price'            => 'required|numeric|min:0',
            // numeric → nombre entier ou décimal
            // min:0   → pas de prix négatif

            'duration_minutes' => 'required|integer|min:15|max:480',
            // integer  → nombre entier uniquement
            // min:15   → durée minimum 15 minutes
            // max:480  → durée maximum 8 heures (480 minutes)

            'is_available'     => 'boolean',
            // Optionnel → true par défaut (défini dans la migration)
        ]);

        $service = Service::create($validated);
        $service->load('category');

        return response()->json([
            'message' => 'Service créé avec succès',
            'data'    => new ServiceResource($service),
        ], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    // Modifie un service existant (admin uniquement)
    {
        $validated = $request->validate([
            'category_id'      => 'sometimes|exists:categories,id',
            // sometimes → valide seulement si le champ est présent
            // Utile pour les mises à jour partielles

            'name'             => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'price'            => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:15|max:480',
            'is_available'     => 'sometimes|boolean',
        ]);

        $service->update($validated);
        $service->load('category');

        return response()->json([
            'message' => 'Service mis à jour avec succès',
            'data'    => new ServiceResource($service),
        ]);
    }

    public function destroy(Service $service): JsonResponse
    // Supprime un service (admin uniquement)
    {
        $bookingsPending = $service->bookings()
            ->where('status', 'pending')
            ->orWhere('status', 'confirmed')
            ->count();
        // Avant de supprimer, on vérifie s'il y a des réservations
        // en cours (pending ou confirmed)
        // On ne peut pas supprimer un service avec des RDV actifs !
        //
        // ->bookings() → accède à la relation hasMany
        // ->where()    → filtre sur le statut
        // ->orWhere()  → OU ce statut
        // ->count()    → compte le nombre de résultats

        if ($bookingsPending > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer ce service : '
                    . $bookingsPending
                    . ' réservation(s) en cours.',
            ], 422);
            // 422 → la logique métier interdit cette action
        }

        $service->delete();

        return response()->json([
            'message' => 'Service supprimé avec succès',
        ]);
    }
}
