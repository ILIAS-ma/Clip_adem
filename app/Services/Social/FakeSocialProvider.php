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

    /**
     * Celles de la plateforme simulée, pour que le contrôle de permissions
     * soit exercé en développement comme en production.
     *
     * @return array<int, string>
     */
    public function requestedScopes(): array
    {
        return match ($this->platform) {
            Platform::TikTok => ['user.info.basic', 'video.list'],
            Platform::YouTube => ['https://www.googleapis.com/auth/youtube.readonly'],
            Platform::Instagram => ['instagram_basic', 'instagram_manage_insights'],
        };
    }

    /** @return array<int, string> */
    public function requiredScopes(): array
    {
        return match ($this->platform) {
            Platform::TikTok => ['video.list'],
            Platform::YouTube => ['https://www.googleapis.com/auth/youtube.readonly'],
            Platform::Instagram => ['instagram_manage_insights'],
        };
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
            scopes: $this->requestedScopes(),
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
            scopes: $account->scopes ?: $this->requestedScopes(),
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

                $views = $this->simulateViews($id, $clip);

                return [$id => new PostMetrics(
                    externalPostId: $id,
                    views: $views,
                    caption: $clip->caption ?? $this->simulateCaption($clip),
                    durationSeconds: $clip->duration_seconds ?? (18 + crc32($id) % 25),
                    postedAt: $clip->posted_at ?? $clip->submitted_at,
                    ownerExternalId: $account->external_account_id,
                    // Taux d'engagement plausibles plutôt que des ratios fixes :
                    // un peu de variation par publication (via crc32 de son id),
                    // mais toujours dans une fourchette réaliste.
                    likes: $this->simulateEngagement($id, $views, .06, .02),
                    comments: $this->simulateEngagement($id, $views, .008, .004),
                    shares: $this->simulateEngagement($id, $views, .015, .01),
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

    /**
     * Un compteur d'engagement plausible pour un nombre de vues donné :
     * taux de base plus une variation déterministe (±) propre à la
     * publication, pour que deux clips à vues égales n'affichent pas
     * exactement le même nombre de likes.
     */
    protected function simulateEngagement(string $externalId, int $views, float $baseRate, float $jitter): int
    {
        $seed = crc32($externalId.'|engagement');
        $rate = $baseRate + ($jitter * (($seed % 1000) / 1000 * 2 - 1));

        return (int) floor($views * max(0, $rate));
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
