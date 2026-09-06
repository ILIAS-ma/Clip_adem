<?php

namespace App\Services\Social;

use App\Enums\Platform;
use App\Exceptions\SocialProviderFailed;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Social\ConnectedAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Liaison, rafraîchissement et déliaison des comptes réseaux.
 *
 * Un compte déjà lié qui se reconnecte est mis à jour, jamais dupliqué :
 * l'unicité porte sur (plateforme, identifiant externe), et les clips existants
 * doivent rester rattachés au même enregistrement.
 */
class SocialAccountLinker
{
    public function __construct(
        protected SocialProviderManager $providers,
    ) {}

    public function link(User $clipper, ConnectedAccount $connected): SocialAccount
    {
        $this->assertScopesGranted($connected);

        $existing = SocialAccount::where('platform', $connected->platform)
            ->where('external_account_id', $connected->externalAccountId)
            ->first();

        // Un compte déjà rattaché à quelqu'un d'autre ne change pas de main :
        // ce serait le moyen le plus simple de récupérer les gains d'un tiers.
        if ($existing && $existing->user_id !== $clipper->getKey()) {
            throw new SocialProviderFailed(sprintf(
                'Ce compte %s est déjà lié à un autre profil.',
                $connected->platform->label(),
            ));
        }

        $account = $existing ?? new SocialAccount([
            'user_id' => $clipper->getKey(),
            'platform' => $connected->platform,
            'external_account_id' => $connected->externalAccountId,
        ]);

        $account->forceFill([
            'user_id' => $clipper->getKey(),
            'platform' => $connected->platform,
            'external_account_id' => $connected->externalAccountId,
            'handle' => $connected->handle,
            'access_token' => $connected->accessToken,
            'refresh_token' => $connected->refreshToken,
            'token_expires_at' => $connected->expiresAt,
            'scopes' => $connected->scopes,
            'followers_count' => $connected->followersCount,
            'verified_at' => now(),
            'is_active' => true,
            'last_refreshed_at' => now(),
            // La reconnexion efface la panne : c'est tout l'intérêt du bandeau
            // d'alerte, il doit disparaître dès que le problème est réglé.
            'needs_reconnect' => false,
            'last_error' => null,
        ])->save();

        return $account;
    }

    /**
     * Refuse une liaison amputée de la permission qui porte les statistiques.
     *
     * Les plateformes laissent l'utilisateur refuser une permission tout en
     * accordant les autres. Un compte lié sans cet accès se comporte
     * normalement partout — il apparaît dans la liste, on peut rejoindre une
     * campagne, publier — puis ne remonte jamais une vue. Le clippeur découvre
     * au bout d'une semaine qu'il ne sera pas payé, et personne ne sait
     * pourquoi. Autant refuser tout de suite, en nommant ce qui manque.
     *
     * Si le fournisseur ne renvoie aucune permission, on ne bloque pas : un
     * refus à tort empêcherait quelqu'un de gagner sa vie, alors qu'un
     * contrôle manqué ne fait que revenir au comportement d'avant.
     */
    protected function assertScopesGranted(ConnectedAccount $connected): void
    {
        $granted = array_filter(array_map('trim', $connected->scopes));

        if ($granted === []) {
            return;
        }

        $missing = array_diff(
            $this->providers->for($connected->platform)->requiredScopes(),
            $granted,
        );

        if ($missing !== []) {
            throw SocialProviderFailed::missingScopes($connected->platform, array_values($missing));
        }
    }

    /**
     * Rafraîchit un jeton avant son expiration.
     *
     * Un échec ne remonte pas : il marque le compte à reconnecter. La
     * synchronisation le sautera au lieu de brûler du quota sur des 401, et le
     * clippeur verra le bandeau d'alerte.
     */
    public function refresh(SocialAccount $account): bool
    {
        try {
            $connected = $this->providers->for($account->platform)->refresh($account);
        } catch (\Throwable $exception) {
            $account->forceFill([
                'needs_reconnect' => true,
                'last_error' => Str::limit($exception->getMessage(), 480),
            ])->save();

            Log::warning('Rafraîchissement de jeton échoué', [
                'social_account_id' => $account->getKey(),
                'platform' => $account->platform->value,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $account->forceFill([
            'access_token' => $connected->accessToken,
            'refresh_token' => $connected->refreshToken ?? $account->refresh_token,
            'token_expires_at' => $connected->expiresAt,
            'followers_count' => $connected->followersCount ?? $account->followers_count,
            'last_refreshed_at' => now(),
            'needs_reconnect' => false,
            'last_error' => null,
        ])->save();

        return true;
    }

    /**
     * Déliaison.
     *
     * Le compte est désactivé, jamais supprimé : les clips déjà soumis y font
     * référence, et leur historique de gains doit rester lisible.
     */
    public function unlink(SocialAccount $account): void
    {
        $account->forceFill([
            'is_active' => false,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'needs_reconnect' => false,
            'last_error' => null,
        ])->save();
    }

    /** Comptes dont le jeton expire bientôt et qu'il faut prolonger. */
    public function expiring(int $withinDays = 7)
    {
        return SocialAccount::query()
            ->where('is_active', true)
            ->where('needs_reconnect', false)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addDays($withinDays))
            ->get();
    }

    public function providerFor(Platform $platform)
    {
        return $this->providers->for($platform);
    }
}
