<?php

namespace App\Notifications;

use App\Models\QuoteRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Accusé de réception envoyé au prospect après sa demande de devis. */
class QuoteReceivedNotification extends Notification
{
    public function __construct(public QuoteRequest $quote) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Nous avons bien reçu votre demande de devis')
            ->greeting("Bonjour {$this->quote->name},")
            ->line("Merci pour votre demande. Un technicien RT Water Solution l'étudie et vous recontacte sous 48 h ouvrées.");

        if ($this->quote->solution_type) {
            $mail->line('Solution souhaitée : ' . $this->quote->solution_type);
        }

        return $mail->line('Pour compléter votre demande, répondez simplement à cet e-mail.');
    }
}
