<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            // Quand la validation échoue → réponse JSON cohérente
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            // Quand le token est absent ou invalide
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Non authentifié — token manquant ou invalide',
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            // Quand l'utilisateur n'a pas les permissions
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Accès refusé — permissions insuffisantes',
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            // Quand un Model n'est pas trouvé (Route Model Binding)
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Ressource introuvable',
                ], 404);
            }
        });
    })

    ->withMiddleware(function (Middleware $middleware): void {

        // ❌ SUPPRIMÉ :
        // $middleware->api(prepend: [
        //     \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // ]);
        // Ce middleware forçait Sanctum à utiliser les sessions
        // au lieu des tokens Bearer → cause de tous nos problèmes

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role'     => \App\Http\Middleware\CheckRole::class,
            // On fusionne les deux alias en un seul appel
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
