<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Notifier;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Informe le client à chaque étape clé : payée, expédiée, livrée, annulée. */
class OrderStatusChangedNotification extends Notification
{
    private const MESSAGES = [
        'paid'      => ['Paiement confirmé', 'Nous avons bien reçu votre paiement. Votre commande part en préparation.'],
        'shipped'   => ['Commande expédiée', 'Bonne nouvelle : votre commande est en route vers votre adresse de livraison.'],
        'delivered' => ['Commande livrée', 'Votre commande a été livrée. Merci de votre confiance ! Votre avis sur les produits aide les autres clients.'],
        'cancelled' => ['Commande annulée', "Votre commande a été annulée. Si vous n'êtes pas à l'origine de cette annulation, contactez-nous."],
    ];

    public function __construct(public Order $order) {}

    public static function supports(string $status): bool
    {
        return array_key_exists($status, self::MESSAGES);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$title, $text] = self::MESSAGES[$this->order->status];

        return (new MailMessage)
            ->subject("{$title} : commande {$this->order->reference}")
            ->greeting('Bonjour ' . ($notifiable->name ?? '') . ',')
            ->line($text)
            ->line("Référence : {$this->order->reference} · Total : " . Notifier::money($this->order->total))
            ->action('Voir ma commande', Notifier::frontendUrl('suivi-commande?ref=' . $this->order->reference));
    }
}
