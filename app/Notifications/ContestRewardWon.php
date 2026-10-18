<?php

namespace App\Notifications;

use App\Enums\ContestMetric;
use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Un lot gagné au classement : un objet, à remettre par l'équipe.
 *
 * Les gains en argent passent par BonusGranted, déjà sur le solde ; ici rien
 * n'est versé, le gagnant doit savoir qu'on va le contacter.
 */
class ContestRewardWon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly int $rank,
        public readonly string $reward,
        public readonly ContestMetric $board = ContestMetric::Views,
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
            'title' => 'Vous avez gagné : '.$this->reward,
            'body' => $this->place().' du '.$this->board->boardName().' de « '.$this->campaign->title.' ». '
                .'L’équipe vous écrit dans Messages pour organiser la remise.',
            'url' => route('messages.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bravo, vous avez gagné : '.$this->reward)
            ->greeting('Bonjour '.$notifiable->displayName().',')
            ->line('Vous terminez **'.$this->place().'** du '.$this->board->boardName().' de « '.$this->campaign->title.' ».')
            ->line('Votre lot : **'.$this->reward.'**. L’équipe vous écrit dans votre messagerie pour organiser la remise.')
            ->action('Ouvrir mes messages', route('messages.index'));
    }

    protected function place(): string
    {
        return $this->rank === 1 ? '1re place' : $this->rank.'e place';
    }
}
