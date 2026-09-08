<?php

namespace App\Notifications;

use App\Enums\PayoutMethod;
use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Le virement est parti. */
class PayoutPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payout $payout) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Retrait versé',
            'body' => 'Votre retrait de '.$this->euros().' a été versé sur '.$this->payout->destinationLabel().'.',
            'url' => route('earnings.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre retrait de '.$this->euros().' a été versé')
            ->greeting('Bonjour '.$notifiable->displayName().',')
            ->line('Votre retrait de **'.$this->euros().'** vient d’être versé sur '
                .$this->payout->destinationLabel().'.')
            // Le délai évite le message « je n'ai rien reçu » deux heures
            // après : l'argent est parti, il n'est pas encore arrivé. Un
            // envoi PayPal manuel arrive aussi vite qu'un envoi automatique
            // — seule la façon dont il a été déclenché diffère.
            ->line($this->payout->payoutMethod() === PayoutMethod::PayPal
                ? 'Il devrait apparaître sur votre compte PayPal sous quelques minutes.'
                : 'Comptez 1 à 3 jours ouvrés avant de le voir sur votre compte.')
            ->action('Voir mes revenus', route('earnings.index'));
    }

    protected function euros(): string
    {
        return number_format($this->payout->amount_cents / 100, 2, ',', ' ').' €';
    }
}
