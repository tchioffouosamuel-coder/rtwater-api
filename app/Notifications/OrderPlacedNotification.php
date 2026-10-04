<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Notifier;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirmation envoyée au client juste après la création de sa commande. */
class OrderPlacedNotification extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items.product');

        $mail = (new MailMessage)
            ->subject("Commande {$order->reference} bien reçue")
            ->greeting('Bonjour ' . ($notifiable->name ?? '') . ',')
            ->line("Merci pour votre commande. Nous l'avons bien reçue et nous la préparons.")
            ->line("**Référence : {$order->reference}**");

        foreach ($order->items as $item) {
            $mail->line('• ' . ($item->product->name ?? 'Article') . " × {$item->quantity} : "
                . Notifier::money($item->price * $item->quantity));
        }

        return $mail
            ->line('**Total : ' . Notifier::money($order->total) . '**')
            ->line('Livraison : ' . $order->address)
            ->action('Suivre ma commande', Notifier::frontendUrl('suivi-commande?ref=' . $order->reference))
            ->line('Une question ? Répondez simplement à cet e-mail.');
    }
}
