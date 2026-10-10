<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Exceptions\SmsFailed;

/**
 * Choisit le pilote d'envoi.
 *
 * Avec des identifiants, le vrai ; sans, le simulé — mais jamais en
 * production. Là, basculer en silence sur les journaux ferait croire qu'un
 * code a été envoyé alors que personne ne le recevra : la personne attendrait
 * un SMS qui n'existe pas, et nous ne le saurions pas. Mieux vaut une panne
 * franche.
 */
class SmsManager
{
    public function driver(): SmsSender
    {
        if ($this->isConfigured()) {
            return new TwilioSmsSender;
        }

        if (app()->isProduction()) {
            throw SmsFailed::notConfigured();
        }

        return new LogSmsSender;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.twilio.account_sid'))
            && filled(config('services.twilio.auth_token'))
            && filled(config('services.twilio.from'));
    }
}
