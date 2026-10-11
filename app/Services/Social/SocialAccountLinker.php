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

        if ($existing && $existing->user_id !== $clipper->getKey()) {
            $this->assertCanChangeHands($existing, $connected->platform);
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
     * Un compte lié ailleurs peut-il passer sur ce profil ?
     *
     * La personne qui arrive ici vient de prouver par OAuth qu'elle détient
     * ce compte TikTok : le refus ne la protège pas d'un vol. Il protège de
     * deux choses précises, et seulement de celles-là.
     *
     * Un compte encore actif sur un autre profil ne bouge pas : sinon deux
     * profils se disputeraient le même compte, et les vues de l'un
     * partiraient au gré des reconnexions.
     *
     * Un compte délié par un profil banni ne bouge pas non plus : c'est le
     * moyen le plus simple de contourner un bannissement — nouveau profil,
     * même compte, mêmes clips qui recommencent à rapporter.
     *
     * Le reste passe. Avant, un compte délié restait rattaché à son ancien
     * profil pour toujours : un TikTok essayé une fois sur un profil de test
     * ne pouvait plus jamais servir ailleurs, et le message ne disait pas
     * quoi faire.
     */
    protected function assertCanChangeHands(SocialAccount $existing, Platform $platform): void
    {
        $previous = $existing->user()->withTrashed()->first();

        if ($previous?->is_banned) {
            throw new SocialProviderFailed(sprintf(
                'Ce compte %s est rattaché à un profil suspendu et ne peut pas être relié.',
                $platform->label(),
            ));
        }

        if ($existing->is_active && $previous !== null && ! $previous->trashed()) {
            throw new SocialProviderFailed(sprintf(
                'Ce compte %s est déjà relié à un autre profil. Déliez-le depuis cet autre '
                .'profil (Mes comptes), ou demandez à un administrateur de le libérer.',
                $platform->label(),
            ));
        }
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
