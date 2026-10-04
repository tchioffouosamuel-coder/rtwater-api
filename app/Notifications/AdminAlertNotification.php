<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerte interne générique pour l'équipe (nouvelle commande, devis, paiement). */
class AdminAlertNotification extends Notification
{
    /** @param string[] $lines */
    public function __construct(
        public string $subject,
        public array $lines,
        public ?string $actionUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('[RT Water] ' . $this->subject);

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        if ($this->actionUrl) {
            $mail->action('Ouvrir le back-office', $this->actionUrl);
        }

        return $mail;
    }
}
