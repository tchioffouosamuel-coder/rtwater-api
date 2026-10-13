<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filtre une route sur une permission précise, plutôt que sur un nom de rôle.
 *
 * Usage : ->middleware('permission:manage_products')
 *         ->middleware('permission:manage_orders,manage_customers')  (l'une suffit)
 *
 * Permet d'ouvrir une route au gestionnaire ou au comptable sans la rouvrir à
 * tout le monde, et sans réécrire les routes quand un rôle change de périmètre.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $granted = Permissions::forUser($user);

        foreach ($permissions as $permission) {
            if (in_array($permission, $granted, true)) {
                return $next($request);
            }
        }

        return response()->json([
            'message'  => "Accès refusé. Votre rôle ne permet pas cette action.",
            'required' => $permissions,
        ], 403);
    }
}
