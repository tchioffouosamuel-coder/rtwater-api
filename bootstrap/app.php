<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

    $middleware->alias([
        'role' => \App\Http\Middleware\CheckRole::class,
        // Donne le nom 'role' à notre middleware
        // Permet d'écrire middleware('role:admin')
        // au lieu de middleware(CheckRole::class . ':admin')
    ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
