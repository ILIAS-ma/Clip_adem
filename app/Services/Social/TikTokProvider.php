<?php

namespace App\Services\Social;

use App\Contracts\SocialProvider;
use App\Enums\Platform;
use App\Exceptions\SocialProviderFailed;
use App\Models\SocialAccount;
use App\Support\Social\ConnectedAccount;
use App\Support\Social\PostMetrics;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Login Kit + Display API.
 *
 * Deux particularités à ne pas oublier : le jeton de rafraîchissement a une
 * durée de vie limitée — contrairement à Google — donc le rafraîchissement
 * planifié n'est pas optionnel ; et la portée `video.list` exige une revue
 * d'application avant de fonctionner hors du mode bac à sable.
 *
 * Non encore éprouvée sur l'API réelle.
 */
class TikTokProvider implements SocialProvider
{
    use ResolvesRedirectUri;

    public function platform(): Platform
    {
        return Platform::TikTok;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.tiktok.client_key'))
            && filled(config('services.tiktok.client_secret'));
    }

    public function redirectUrl(string $state): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => config('services.tiktok.client_key'),
            'scope' => implode(',', $this->requestedScopes()),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);
    }

    /**
     * `user.info.basic` identifie le compte, `video.list` donne les vues.
     *
     * @return array<int, string>
     */
    public function requestedScopes(): array
    {
        return ['user.info.basic', 'user.info.stats', 'video.list'];
    }

    /**
     * Seule `video.list` est vérifiée : sans elle aucune vue ne remonte, donc
     * aucun euro. `user.info.basic` refusée fait déjà échouer `connect()`, qui
     * ne peut alors pas lire l'`open_id`.
     *
     * @return array<int, string>
     */
    public function requiredScopes(): array
    {
        return ['video.list'];
    }

    public function connect(string $code): ConnectedAccount
    {
        $token = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        if ($token->failed()) {
            throw SocialProviderFailed::tokenExchange($this->platform(), $token->status(), $token->body());
        }

        return $this->accountFrom($token->json());
    }

    public function refresh(SocialAccount $account): ConnectedAccount
    {
        if (blank($account->refresh_token)) {
            throw SocialProviderFailed::missingRefreshToken($this->platform());
        }

        $token = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($token->failed()) {
            throw SocialProviderFailed::refreshFailed($this->platform(), $token->status(), $token->body());
        }

        return $this->accountFrom($token->json(), $account);
    }

    public function fetchPosts(SocialAccount $account, array $externalIds): Collection
    {
        if ($externalIds === []) {
            return collect();
        }

        $response = Http::withToken($account->access_token)
            ->asJson()
            ->post('https://open.tiktokapis.com/v2/video/query/?fields='.implode(',', [
                'id', 'view_count', 'video_description', 'duration', 'create_time',
                'like_count', 'comment_count', 'share_count',
            ]), [
                'filters' => ['video_ids' => array_values($externalIds)],
            ]);

        if ($response->failed()) {
            throw SocialProviderFailed::fetchFailed($this->platform(), $response->status(), $response->body());
        }

        return collect($response->json('data.videos', []))
            ->mapWithKeys(fn (array $video) => [
                (string) $video['id'] => new PostMetrics(
                    externalPostId: (string) $video['id'],
                    views: (int) ($video['view_count'] ?? 0),
                    caption: $video['video_description'] ?? null,
                    durationSeconds: isset($video['duration']) ? (int) $video['duration'] : null,
                    postedAt: isset($video['create_time']) ? Carbon::createFromTimestamp($video['create_time']) : null,
                    ownerExternalId: $account->external_account_id,
                    likes: (int) ($video['like_count'] ?? 0),
                    comments: (int) ($video['comment_count'] ?? 0),
                    shares: (int) ($video['share_count'] ?? 0),
                ),
            ]);
    }

    public function batchSize(): int
    {
        return 20;
    }

    public function quotaCostPerCall(): int
    {
        return 1;
    }

    public function dailyQuota(): ?int
    {
        return config('services.tiktok.daily_quota');
    }

    /** Lit le profil avec la liste de champs demandée. */
    protected function readProfile(string $accessToken, string $fields): Response
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => $fields]);
    }

    /** @param  array<string, mixed>  $token */
    protected function accountFrom(array $token, ?SocialAccount $existing = null): ConnectedAccount
    {
        /*
         * `follower_count` relève de la portée `user.info.stats`, distincte de
         * `user.info.basic`. Demander le champ sans la portée fait échouer
         * TOUT l'appel avec un « scope_not_authorized » — et donc la liaison
         * du compte, alors que le nombre d'abonnés n'est qu'un signal de
         * fraude secondaire.
         *
         * On tente donc avec, puis sans. Perdre un indicateur de modération
         * vaut mieux qu'empêcher quelqu'un de lier son compte.
         */
        $user = $this->readProfile($token['access_token'], 'open_id,display_name,follower_count');

        if ($user->failed() && $user->status() === 401) {
            $user = $this->readProfile($token['access_token'], 'open_id,display_name');
        }

        if ($user->failed()) {
            throw SocialProviderFailed::fetchFailed($this->platform(), $user->status(), $user->body());
        }

        $profile = $user->json('data.user', []);

        return new ConnectedAccount(
            platform: $this->platform(),
            externalAccountId: $token['open_id'] ?? $profile['open_id'],
            handle: $profile['display_name'] ?? null,
            accessToken: $token['access_token'],
            refreshToken: $token['refresh_token'] ?? $existing?->refresh_token,
            expiresAt: now()->addSeconds((int) ($token['expires_in'] ?? 86400)),
            scopes: explode(',', (string) ($token['scope'] ?? '')),
            followersCount: isset($profile['follower_count']) ? (int) $profile['follower_count'] : null,
        );
    }
}
