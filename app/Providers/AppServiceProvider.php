<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                    // Dit à Scramble que l'API utilise
                    // l'authentification par Bearer Token
                    // → Ajoute un bouton 🔒 Authorize dans Scramble
                    // → Tu pourras coller ton token une seule fois
                    //   et il sera envoyé automatiquement sur toutes
                    //   les requêtes protégées
                );
            });

        Scramble::configure()
            ->routes(function ($route) {
                return str_starts_with($route->uri, 'api/') ||
                    str_starts_with($route->uri, 'login') ||
                    str_starts_with($route->uri, 'register') ||
                    str_starts_with($route->uri, 'logout');
                // Dit à Scramble quelles routes inclure dans la doc
                // Par défaut il prend seulement les routes api/*
                // On ajoute login, register, logout de Breeze
            });
    }
}
