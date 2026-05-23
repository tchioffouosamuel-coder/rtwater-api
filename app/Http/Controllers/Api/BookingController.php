<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    // Liste les réservations de l'utilisateur connecté
    {
        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            // L'user ne voit que SES réservations

            ->with('service.category')
            // Charge le service ET sa catégorie
            // service          → relation belongsTo vers Service
            // service.category → relation imbriquée : Service → Category

            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            // Filtre optionnel par statut
            // GET /api/bookings?status=confirmed

            ->when($request->upcoming, function ($query) {
                $query->where('scheduled_at', '>', now());
            })
            // Filtre optionnel : seulement les RDV à venir
            // GET /api/bookings?upcoming=1
            // now() → date et heure actuelles

            ->orderBy('scheduled_at', 'asc')
            // Trie par date de RDV croissante
            // Les prochains RDV apparaissent en premier
            // asc = ascendant = du plus ancien au plus récent

            ->paginate(10);

        return response()->json([
            'data' => BookingResource::collection($bookings),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page'    => $bookings->lastPage(),
                'total'        => $bookings->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    // Crée une nouvelle réservation
    {
        $validated = $request->validate([
            'service_id'   => 'required|exists:services,id',
            // exists → le service doit exister en DB

            'scheduled_at' => 'required|date|after:now',
            // date       → doit être une date valide
            // after:now  → la date doit être dans le futur
            // On ne peut pas réserver dans le passé !

            'notes'        => 'nullable|string|max:500',
        ]);

        $service = Service::find($validated['service_id']);
        // Récupère le service pour vérifications

        if (!$service->is_available) {
            return response()->json([
                'message' => 'Ce service n\'est pas disponible actuellement',
            ], 422);
            // Vérifie que le service est disponible
        }

        // Vérification des conflits de créneaux
        $conflict = Booking::where('service_id', $validated['service_id'])
            ->where('status', '!=', 'cancelled')
            // On ignore les réservations annulées
            // '!=' → différent de

            ->where(function ($query) use ($validated, $service) {
                $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_at']);
                // Carbon::parse() → convertit la string de date
                // en objet Carbon pour faire des calculs dessus
                // "2024-03-15 14:00:00" → objet Carbon

                $endTime = $scheduledAt->copy()->addMinutes($service->duration_minutes);
                // ->copy() → crée une copie de l'objet Carbon
                // IMPORTANT : sans copy(), addMinutes() modifierait
                // l'objet original $scheduledAt !
                //
                // ->addMinutes(60) → ajoute 60 minutes
                // Si RDV à 14:00 et durée = 60 min → fin à 15:00

                $query->whereBetween('scheduled_at', [
                    $scheduledAt,
                    $endTime
                ]);
                // whereBetween → cherche un RDV dont scheduled_at
                // est entre le début et la fin du nouveau RDV
                // Si un RDV existe dans ce créneau → conflit !
            })
            ->exists();
        // exists() → retourne true si au moins un résultat existe
        // Plus efficace que count() > 0 car s'arrête au premier résultat trouvé

        if ($conflict) {
            return response()->json([
                'message' => 'Ce créneau est déjà réservé pour ce service',
            ], 422);
        }

        $booking = Booking::create([
            'user_id'      => $request->user()->id,
            'service_id'   => $validated['service_id'],
            'scheduled_at' => $validated['scheduled_at'],
            'notes'        => $validated['notes'] ?? null,
            // ?? null → opérateur "null coalescing"
            // Si $validated['notes'] existe → utilise sa valeur
            // Si notes n'a pas été envoyé → null
            'status'       => 'pending',
            // Toute nouvelle réservation commence en 'pending'
        ]);

        $booking->load('service.category', 'user');

        return response()->json([
            'message' => 'Réservation créée avec succès',
            'data'    => new BookingResource($booking),
        ], 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    // Affiche le détail d'une réservation
    {
        if ($booking->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Accès non autorisé',
            ], 403);
            // Seul l'owner de la réservation ou un admin peut la voir
        }

        $booking->load('service.category', 'user');

        return response()->json([
            'data' => new BookingResource($booking),
        ]);
    }

    public function update(Request $request, Booking $booking): JsonResponse
    // Modifie une réservation (annulation par le client, confirmation par admin)
    {
        $isOwner = $booking->user_id === $request->user()->id;
        $isAdmin = $request->user()->isAdmin();
        // On calcule ces variables une seule fois
        // pour les réutiliser proprement

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'message' => 'Accès non autorisé',
            ], 403);
        }

        // Règles différentes selon qui fait la modification
        if ($isOwner && !$isAdmin) {
            // Le CLIENT ne peut qu'annuler sa réservation
            $validated = $request->validate([
                'status' => 'required|in:cancelled',
                // Le client ne peut que annuler
                // Il ne peut pas confirmer ou compléter lui-même

                'notes'  => 'sometimes|nullable|string|max:500',
                // Il peut aussi modifier ses notes
            ]);

            if ($booking->status !== 'pending') {
                return response()->json([
                    'message' => 'Seules les réservations en attente peuvent être annulées',
                ], 422);
                // On ne peut pas annuler une réservation déjà confirmée
                // ou déjà complétée
            }
        } else {
            // L'ADMIN peut changer n'importe quel statut
            $validated = $request->validate([
                'status' => 'sometimes|in:pending,confirmed,cancelled,completed',
                'notes'  => 'sometimes|nullable|string|max:500',
            ]);
        }

        $booking->update($validated);
        $booking->load('service.category', 'user');

        return response()->json([
            'message' => 'Réservation mise à jour avec succès',
            'data'    => new BookingResource($booking),
        ]);
    }

    public function destroy(Request $request, Booking $booking): JsonResponse
    // Supprime définitivement une réservation (admin uniquement)
    {
        if ($booking->status === 'completed') {
            return response()->json([
                'message' => 'Une réservation complétée ne peut pas être supprimée',
            ], 422);
            // On garde toujours l'historique des prestations effectuées
            // Pour la comptabilité et le suivi client
        }

        $booking->delete();

        return response()->json([
            'message' => 'Réservation supprimée avec succès',
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    // Vue admin : toutes les réservations de tous les clients
    {
        $bookings = Booking::query()
            ->with('service.category', 'user')
            // Charge service, catégorie du service, et utilisateur
            // pour afficher toutes les infos dans la vue admin

            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            // GET /api/admin/bookings?status=pending

            ->when($request->service_id, function ($query, $serviceId) {
                $query->where('service_id', $serviceId);
            })
            // GET /api/admin/bookings?service_id=2

            ->when($request->date, function ($query, $date) {
                $query->whereDate('scheduled_at', $date);
            })
            // Filtre par date exacte
            // GET /api/admin/bookings?date=2024-03-15
            // whereDate() → compare seulement la partie DATE (pas l'heure)
            // "2024-03-15 14:00:00" → compare "2024-03-15"

            ->when($request->user_id, function ($query, $userId) {
                $query->where('user_id', $userId);
            })
            // Filtre par client
            // GET /api/admin/bookings?user_id=7

            ->orderBy('scheduled_at', 'asc')
            ->paginate(20);

        return response()->json([
            'data' => BookingResource::collection($bookings),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page'    => $bookings->lastPage(),
                'total'        => $bookings->total(),
            ],
        ]);
    }
}
