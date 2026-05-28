<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventLazyLoading(
            ! app()->isProduction()
        );
        // preventLazyLoading() → Laravel lance une EXCEPTION
        // si tu oublies un with() et qu'une relation est chargée
        // en lazy loading (N+1)
        //
        // ! app()->isProduction()
        // → Actif seulement en développement
        // → En production → pas d'exception (évite de casser le site)
        // → En développement → t'oblige à corriger le N+1

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
    }
}
