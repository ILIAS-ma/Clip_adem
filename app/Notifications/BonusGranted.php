<?php

namespace App\Notifications;

use App\Models\Clip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Une prime vient d'être créditée.
 *
 * Le tableau de bord lit ces notifications non lues pour la célébrer : c'est
 * ce qui fait d'une prime un encouragement plutôt qu'une ligne de plus dans
 * l'historique.
 */
class BonusGranted extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'bonus_granted';

    public function __construct(
        public readonly Clip $clip,
        public readonly int $amountCents,
        public readonly string $reason,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function databaseType(object $notifiable): string
    {
        return self::TYPE;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Prime de '.$this->euros().' accordée',
            'body' => $this->reason.'. Elle est déjà dans votre solde.',
            'url' => route('earnings.index'),
            'amount_cents' => $this->amountCents,
            'clip_id' => $this->clip->getKey(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vous avez reçu une prime de '.$this->euros())
            ->greeting('Bonjour '.$notifiable->displayName().',')
            ->line($this->reason.' : **'.$this->euros().'** viennent d’être crédités sur votre solde.')
            ->line('Continuez comme ça.')
            ->action('Voir mes revenus', route('earnings.index'));
    }

    protected function euros(): string
    {
        return number_format($this->amountCents / 100, 2, ',', ' ').' €';
    }
}
