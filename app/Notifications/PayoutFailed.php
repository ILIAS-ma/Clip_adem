<?php

namespace App\Notifications;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le versement a échoué et le solde est redevenu disponible.
 *
 * Sans cet e-mail, l'argent réapparaît dans le solde sans explication : la
 * lecture la plus naturelle est « on ne m'a pas payé », pas « le virement a
 * été rejeté ».
 */
class PayoutFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Payout $payout,
        public readonly ?string $reason = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $amount = number_format($this->payout->amount_cents / 100, 2, ',', ' ').' €';

        return [
            'title' => 'Retrait échoué',
            'body' => 'Le versement de '.$amount.' a été rejeté. Le montant est de nouveau dans votre solde.',
            'url' => route('payout-method.edit'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->payout->amount_cents / 100, 2, ',', ' ').' €';

        $message = (new MailMessage)
            ->subject('Votre retrait de '.$amount.' n’a pas pu être versé')
            ->greeting('Bonjour '.$notifiable->displayName().',')
            ->line('Le versement de **'.$amount.'** vers '.$this->payout->destinationLabel()
                .' a été rejeté.')
            ->line('Ce montant est de nouveau disponible dans votre solde : rien n’est perdu.');

        if ($this->reason) {
            $message->line('**Raison communiquée :** '.$this->reason);
        }

        return $message
            ->line('Vérifiez votre moyen de paiement avant de redemander un retrait.')
            ->action('Vérifier mon moyen de paiement', route('payout-method.edit'));
    }
}
