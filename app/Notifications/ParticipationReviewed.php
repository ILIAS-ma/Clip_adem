<?php

namespace App\Notifications;

use App\Models\CampaignParticipation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** L'équipe a accepté, ou refusé, une demande de participation. */
class ParticipationReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CampaignParticipation $participation,
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
        $title = $this->participation->campaign?->title;

        return [
            'title' => $this->accepted ? 'Participation acceptée' : 'Participation refusée',
            'body' => $this->accepted
                ? 'Vous pouvez publier et soumettre vos clips sur « '.$title.' ».'
                : 'Votre participation à « '.$title.' » n’a pas été retenue : '.$this->participation->rejection_reason,
            'url' => $this->participation->campaign ? route('campaigns.show', $this->participation->campaign) : route('campaigns.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'].' · '.$this->participation->campaign?->title)
            ->greeting('Bonjour '.$notifiable->displayName().',')
            ->line($data['body'])
            ->action('Voir la campagne', $data['url']);
    }
}
