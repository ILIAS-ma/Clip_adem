<?php

namespace App\Services\Social;

use App\Contracts\SocialProvider;
use App\Enums\Platform;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Support\Social\ConnectedAccount;
use App\Support\Social\PostMetrics;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fournisseur simulé.
 *
 * Il existe pour une raison précise : les revues d'application TikTok et Meta
 * prennent plusieurs jours ouvrés, et tout le reste du produit — conformité,
 * synchronisation, crédit du budget, tableau de bord — ne doit pas les
 * attendre. Le jour où les vraies clés arrivent, seule l'implémentation change.
 *
 * Les vues suivent une courbe déterministe : une même publication renvoie
 * toujours le même nombre de vues à un instant donné, et ce nombre croît de
 * façon plausible puis sature. Un aléatoire pur rendrait les tests instables et
 * ferait parfois baisser les vues sans raison.
 */
class FakeSocialProvider implements SocialProvider
{
    public function __construct(
        protected Platform $platform,
    ) {}

    public function platform(): Platform
    {
        return $this->platform;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function redirectUrl(string $state): string
    {
        return route('social.callback', [
            'platform' => $this->platform->value,
            'state' => $state,
            'code' => 'fake-'.Str::random(24),
        ]);
    }

    public function connect(string $code): ConnectedAccount
    {
        $seed = crc32($code.$this->platform->value);

        return new ConnectedAccount(
            platform: $this->platform,
            externalAccountId: 'demo-'.$this->platform->value.'-'.substr(md5($code), 0, 12),
            handle: 'demo'.($seed % 9000 + 1000),
            accessToken: 'fake-access-'.Str::random(32),
            refreshToken: 'fake-refresh-'.Str::random(32),
            expiresAt: now()->addDays(60),
            scopes: ['read.profile', 'read.metrics'],
            followersCount: $seed % 90_000 + 1_000,
        );
    }

    public function refresh(SocialAccount $account): ConnectedAccount
    {
        return new ConnectedAccount(
            platform: $this->platform,
            externalAccountId: $account->external_account_id,
            handle: $account->handle,
            accessToken: 'fake-access-'.Str::random(32),
            refreshToken: 'fake-refresh-'.Str::random(32),
            expiresAt: now()->addDays(60),
            scopes: $account->scopes ?? ['read.profile', 'read.metrics'],
            followersCount: $account->followers_count,
        );
    }

    public function fetchPosts(SocialAccount $account, array $externalIds): Collection
    {
        $clips = Clip::whereIn('external_post_id', $externalIds)
            ->where('platform', $this->platform)
            ->get()
            ->keyBy('external_post_id');

        return collect($externalIds)
            ->mapWithKeys(function (string $id) use ($clips, $account) {
                $clip = $clips->get($id);

                if (! $clip) {
                    return [];
                }

                return [$id => new PostMetrics(
                    externalPostId: $id,
                    views: $this->simulateViews($id, $clip),
                    caption: $clip->caption ?? $this->simulateCaption($clip),
                    durationSeconds: $clip->duration_seconds ?? (18 + crc32($id) % 25),
                    postedAt: $clip->posted_at ?? $clip->submitted_at,
                    ownerExternalId: $account->external_account_id,
                )];
            });
    }

    public function batchSize(): int
    {
        return 50;
    }

    public function quotaCostPerCall(): int
    {
        return 1;
    }

    public function dailyQuota(): ?int
    {
        return null;
    }

    /**
     * Courbe de vues plausible : montée rapide les premières heures, puis
     * saturation. Déterministe à l'heure près, pour que deux synchros
     * rapprochées ne fassent pas osciller le compteur.
     */
    protected function simulateViews(string $externalId, Clip $clip): int
    {
        $seed = crc32($externalId);
        $reference = $clip->posted_at ?? $clip->submitted_at ?? $clip->created_at;
        $hours = max(0, (int) $reference->diffInHours(now()));

        // Plafond propre à la publication : de 5 000 à environ 500 000 vues.
        $ceiling = 5_000 + $seed % 495_000;

        // Saturation exponentielle : ~63 % du plafond au bout de 72 h.
        $progress = 1 - exp(-$hours / 72);

        return (int) floor($ceiling * $progress);
    }

    protected function simulateCaption(Clip $clip): string
    {
        $hashtags = $clip->campaign?->required_hashtags ?? [];

        // Un clip sur cinq oublie un hashtag : sans ça, le contrôle de
        // conformité n'aurait jamais rien à signaler en démonstration.
        if ($hashtags && crc32($clip->external_post_id) % 5 === 0) {
            array_pop($hashtags);
        }

        return trim('Découvrez ce son 🔥 '.implode(' ', $hashtags));
    }
}
