<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use App\Support\Notifier;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
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

        // Le lien « mot de passe oublié » pointe vers le site React, pas vers l'API
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return Notifier::frontendUrl('reset-password?token=' . $token . '&email=' . urlencode($user->email));
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = Notifier::frontendUrl('reset-password?token=' . $token . '&email=' . urlencode($notifiable->email));

            return (new MailMessage)
                ->subject('Réinitialisation de votre mot de passe')
                ->greeting('Bonjour ' . ($notifiable->name ?? '') . ',')
                ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte RT Water Solution.')
                ->action('Choisir un nouveau mot de passe', $url)
                ->line('Ce lien expire dans 60 minutes.')
                ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e-mail.");
        });

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
    }
}
