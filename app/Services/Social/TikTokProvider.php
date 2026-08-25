<?php

namespace App\Services\Social;

use App\Contracts\SocialProvider;
use App\Enums\Platform;
use App\Exceptions\SocialProviderFailed;
use App\Models\SocialAccount;
use App\Support\Social\ConnectedAccount;
use App\Support\Social\PostMetrics;
use Carbon\Carbon;
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
            'scope' => 'user.info.basic,video.list',
            'response_type' => 'code',
            'redirect_uri' => route('social.callback', ['platform' => $this->platform()->value]),
            'state' => $state,
        ]);
    }

    public function connect(string $code): ConnectedAccount
    {
        $token = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('social.callback', ['platform' => $this->platform()->value]),
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

    /** @param  array<string, mixed>  $token */
    protected function accountFrom(array $token, ?SocialAccount $existing = null): ConnectedAccount
    {
        $user = Http::withToken($token['access_token'])
            ->acceptJson()
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,display_name,follower_count',
            ]);

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
