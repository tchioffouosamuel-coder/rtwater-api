<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        // Vérifie email + password
        // Si incorrect → exception → réponse 422 automatique

        $request->session()->regenerate();
        // Régénère l'ID de session pour éviter les attaques
        // de fixation de session

        $token = $request->user()->createToken('api-token')->plainTextToken;
        // createToken('api-token') → crée un nouveau token Sanctum
        // plainTextToken → retourne le token en clair (une seule fois !)
        // Format : "1|abcdef123456..."

        return response()->json([
            'message' => 'Connexion réussie',
            'token'   => $token,
            'user'    => [
                'id'    => $request->user()->id,
                'name'  => $request->user()->name,
                'email' => $request->user()->email,
                'roles' => $request->user()->roles->pluck('name'),
                // pluck('name') → extrait seulement les noms des rôles
                // ['admin'] ou ['user'] etc.
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        // Supprime le token actuel de la DB
        // L'user est déconnecté → le token ne fonctionne plus

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }
}
