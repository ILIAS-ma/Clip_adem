<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vérifie un jeton Cloudflare Turnstile auprès de l'API siteverify.
 *
 * L'échec de l'appel réseau lui-même (Cloudflare indisponible) ne bloque pas
 * l'inscription : mieux vaut laisser passer que de mettre l'inscription
 * entière à la merci d'un tiers en panne.
 */
class ValidTurnstile implements ValidationRule
{
    public function __construct(private readonly ?string $ip) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Merci de valider la vérification anti-robot.');

            return;
        }

        $secret = config('services.turnstile.secret_key');

        if (blank($secret)) {
            return;
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => $this->ip,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Turnstile siteverify indisponible', ['exception' => $e->getMessage()]);

            return;
        }

        if (! $response->json('success', false)) {
            $fail('Vérification anti-robot échouée. Réessayez.');
        }
    }
}
