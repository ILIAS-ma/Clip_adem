<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Message envoyé par l'administration à un groupe d'utilisateurs.
 *
 * Mis en file, toujours : envoyer trois cents e-mails dans le cycle d'une
 * requête HTTP la fait expirer au bout de quelques dizaines, et personne ne
 * sait alors qui a reçu quoi.
 */
class AdminBroadcast extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $body,
        public readonly ?string $actionLabel = null,
        public readonly ?string $actionUrl = null,
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
            'title' => $this->subjectLine,
            'body' => trim(preg_split('/\n\s*\n/', trim($this->body))[0] ?? ''),
            'url' => $this->actionUrl,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subjectLine)
            ->greeting('Bonjour '.$notifiable->displayName().',');

        // Un paragraphe par ligne vide : coller le texte brut d'un seul bloc
        // produit un pavé illisible sur mobile.
        foreach (preg_split('/\n\s*\n/', trim($this->body)) as $paragraph) {
            if (trim($paragraph) !== '') {
                $message->line(trim($paragraph));
            }
        }

        if ($this->actionLabel && $this->actionUrl) {
            $message->action($this->actionLabel, $this->actionUrl);
        }

        return $message;
    }
}
