<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Pilote de développement : le message part dans les journaux.
 *
 * Il rend le parcours complet éprouvable sans compte chez un prestataire et
 * sans dépenser un centime. Le code s'y lit en clair — c'est voulu, et c'est
 * précisément pourquoi `SmsManager` refuse ce pilote en production.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS simulé', ['to' => $to, 'message' => $message]);
    }

    public function name(): string
    {
        return 'journal';
    }

    public function isSimulated(): bool
    {
        return true;
    }
}
