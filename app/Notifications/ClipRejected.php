<?php

namespace App\Notifications;

use App\Models\Clip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le clip a été refusé ou invalidé.
 *
 * C'est la notification la plus importante du produit : sans elle, un clippeur
 * continue de publier de la même façon en croyant que tout va bien, et
 * découvre le problème sur son solde des semaines plus tard. Le motif est donc
 * repris tel quel — un refus sans raison ne corrige aucun comportement.
 */
class ClipRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Clip $clip,
        public readonly string $reason,
        public readonly bool $wasPaid = false,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Votre clip n’a pas été retenu — '.$this->clip->campaign?->title)
            ->greeting('Bonjour '.$notifiable->displayName().',')
            ->line('Votre clip publié sur '.$this->clip->platform->label()
                .' pour la campagne « '.$this->clip->campaign?->title.' » n’a pas été retenu.')
            ->line('**Motif :** '.$this->reason);

        if ($this->wasPaid) {
            // Dire que le solde baisse avant que la personne le constate :
            // l'apprendre en regardant ses revenus est le meilleur moyen de
            // croire à une erreur de la plateforme.
            $message->line(
                'Les vues de ce clip avaient déjà été rémunérées : le montant correspondant '
                .'a été retiré de votre solde.'
            );
        }

        return $message
            ->action('Voir le clip', route('clips.show', $this->clip))
            ->line('Si vous pensez qu’il s’agit d’une erreur, répondez à cet e-mail.');
    }
}
