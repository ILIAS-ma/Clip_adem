<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Exceptions\SmsFailed;
use Illuminate\Support\Facades\Http;

/**
 * Envoi réel, via l'API REST de Twilio.
 *
 * Appel direct plutôt que le paquet officiel : une seule requête est
 * nécessaire, et la dépendance tirerait tout un SDK pour elle.
 */
class TwilioSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        $sid = (string) config('services.twilio.account_sid');
        $token = (string) config('services.twilio.auth_token');
        $from = (string) config('services.twilio.from');

        if ($sid === '' || $token === '' || $from === '') {
            throw SmsFailed::notConfigured();
        }

        $reponse = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $message,
            ]);

        if ($reponse->failed()) {
            throw SmsFailed::refused($reponse->status(), $reponse->body());
        }
    }

    public function name(): string
    {
        return 'twilio';
    }

    public function isSimulated(): bool
    {
        return false;
    }
}
