<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Envoi d'e-mails transactionnels « best effort » :
 * une panne SMTP ne doit jamais faire échouer une commande ou un paiement.
 */
class Notifier
{
    /** Les adresses générées pour les invités sans e-mail ne reçoivent rien. */
    public static function isDeliverable(?string $email): bool
    {
        return $email
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && !str_ends_with($email, '@example.com');
    }

    public static function toUser($user, Notification $notification): void
    {
        if (!$user || !self::isDeliverable($user->email)) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Échec envoi notification client', [
                'notification' => $notification::class,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    public static function toEmail(?string $email, Notification $notification): void
    {
        if (!self::isDeliverable($email)) {
            return;
        }

        try {
            NotificationFacade::route('mail', $email)->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Échec envoi notification e-mail', [
                'notification' => $notification::class,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /** Alerte interne pour l'équipe (nouvelle commande, nouveau devis, paiement). */
    public static function toAdmin(Notification $notification): void
    {
        self::toEmail(config('mail.admin_address'), $notification);
    }

    public static function money($amount): string
    {
        return number_format((float) $amount, 0, ',', ' ') . ' XAF';
    }

    public static function frontendUrl(string $path = ''): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . '/' . ltrim($path, '/');
    }
}
