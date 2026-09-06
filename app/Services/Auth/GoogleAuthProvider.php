<?php

namespace App\Services\Auth;

use App\Exceptions\SocialProviderFailed;
use App\Support\Auth\GoogleProfile;
use Illuminate\Support\Facades\Http;

/**
 * Connexion Google, écrite à la main.
 *
 * Socialite n'était pas installable — il exige Guzzle 7 quand le projet tourne
 * sur Guzzle 8 — et descendre une bibliothèque HTTP centrale pour une page de
 * connexion serait un mauvais échange. Le projet parle déjà OAuth avec TikTok,
 * YouTube et Instagram : c'est le même patron, et il reste sous notre
 * contrôle.
 */
class GoogleAuthProvider
{
    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /** URL de consentement, avec le jeton anti-CSRF de la session. */
    public function redirectUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,

            // `select_account` plutôt que rien : sans ça, quelqu'un connecté à
            // plusieurs comptes Google est reconnecté silencieusement au
            // dernier utilisé, sans jamais pouvoir en changer.
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Échange le code contre le profil.
     *
     * @throws SocialProviderFailed
     */
    public function profileFrom(string $code): GoogleProfile
    {
        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        if ($token->failed()) {
            throw new SocialProviderFailed(
                'Google a refusé la connexion. Réessayez, ou utilisez votre mot de passe.'
            );
        }

        /*
         * Le profil est relu par l'API plutôt que décodé depuis l'`id_token`.
         * Vérifier une signature JWT à la main est exactement le genre de code
         * qu'on écrit une fois, mal, et qui laisse passer un jeton forgé. La
         * réponse de `userinfo` arrive, elle, par un canal TLS déjà authentifié.
         */
        $profile = Http::withToken($token->json('access_token'))
            ->acceptJson()
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if ($profile->failed() || blank($profile->json('sub'))) {
            throw new SocialProviderFailed('Impossible de lire votre profil Google.');
        }

        return new GoogleProfile(
            googleId: (string) $profile->json('sub'),
            email: (string) $profile->json('email'),
            emailVerified: (bool) $profile->json('email_verified'),
            name: $profile->json('name') ?: null,
            givenName: $profile->json('given_name') ?: null,
            familyName: $profile->json('family_name') ?: null,
            avatarUrl: $profile->json('picture') ?: null,
        );
    }

    /**
     * L'adresse de retour, figeable en configuration.
     *
     * Google la compare caractère par caractère avec celle enregistrée : la
     * déduire de l'hôte de la requête casse derrière un tunnel ou un
     * répartiteur de charge, avec un message qui n'explique rien.
     */
    public function redirectUri(): string
    {
        return config('services.google.redirect') ?: route('google.callback');
    }
}
