<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    // $next    → la prochaine étape (le controller)
    // ...$roles → les rôles autorisés (variadic = plusieurs valeurs possibles)
    // string ...$roles permet d'écrire :
    // middleware('role:admin')
    // middleware('role:admin,gestionnaire')
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Non authentifié',
            ], 401);
            // 401 = Unauthorized → pas connecté
        }

        foreach ($roles as $role) {
            // On parcourt chaque rôle autorisé
            if ($request->user()->hasRole($role)) {
                return $next($request);
                // Si l'user a ce rôle → on laisse passer la requête
                // $next($request) → passe au controller
            }
        }

        return response()->json([
            'message' => 'Accès refusé. Vous n\'avez pas les permissions nécessaires.',
        ], 403);
        // 403 = Forbidden → connecté mais pas autorisé
        // Aucun des rôles requis n'a été trouvé → accès refusé
    }
}
