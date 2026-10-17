<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** L'équipe a vérifié, ou refusé, le SIRET déclaré. */
class SiretReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $clipper,
        public readonly bool $accepted,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->accepted ? 'SIRET vérifié' : 'SIRET refusé',
            'body' => $this->accepted
                ? 'Votre statut est validé : le plafond annuel de retraits est levé.'
                : 'Votre SIRET n’a pas pu être vérifié : '.$this->clipper->siret_rejection_reason,
            'url' => route('payout-method.edit'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->accepted ? 'Votre SIRET est vérifié' : 'Votre SIRET n’a pas pu être vérifié')
            ->greeting('Bonjour '.$notifiable->displayName().',');

        return $this->accepted
            ? $mail->line('Votre statut d’auto-entrepreneur est validé : vos retraits ne sont plus plafonnés.')
                ->action('Voir mes revenus', route('earnings.index'))
            : $mail->line('Motif : '.$this->clipper->siret_rejection_reason)
                ->line('Corrigez le numéro depuis votre page « Moyen de paiement » : il sera vérifié à nouveau.')
                ->action('Corriger mon SIRET', route('payout-method.edit'));
    }
}
