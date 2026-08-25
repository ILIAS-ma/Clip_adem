<?php

namespace App\Services\Social;

use App\Contracts\SocialProvider;
use App\Enums\Platform;
use App\Exceptions\SocialProviderFailed;
use App\Models\SocialAccount;
use App\Support\Social\ConnectedAccount;
use App\Support\Social\PostMetrics;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Google OAuth + YouTube Data API v3.
 *
 * La plus simple des trois : pas de revue d'application pour lire des
 * statistiques publiques, un jeton de rafraîchissement à longue durée, et un
 * quota généreux dès lors qu'on interroge les vidéos par lots.
 *
 * Non encore éprouvée sur l'API réelle : le projet n'a pas eu de clés à ce
 * jour. Les points à revalider en premier sont marqués « À VÉRIFIER ».
 */
class YouTubeProvider implements SocialProvider
{
    public function platform(): Platform
    {
        return Platform::YouTube;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.youtube.client_id'))
            && filled(config('services.youtube.client_secret'));
    }

    public function redirectUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.youtube.client_id'),
            'redirect_uri' => route('social.callback', ['platform' => $this->platform()->value]),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/youtube.readonly',
            // Sans « offline » et « consent », Google ne renvoie pas de jeton de
            // rafraîchissement à la deuxième connexion du même compte.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function connect(string $code): ConnectedAccount
    {
        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.youtube.client_id'),
            'client_secret' => config('services.youtube.client_secret'),
            'redirect_uri' => route('social.callback', ['platform' => $this->platform()->value]),
            'grant_type' => 'authorization_code',
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

        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.youtube.client_id'),
            'client_secret' => config('services.youtube.client_secret'),
            'grant_type' => 'refresh_token',
        ]);

        if ($token->failed()) {
            throw SocialProviderFailed::refreshFailed($this->platform(), $token->status(), $token->body());
        }

        return $this->accountFrom(
            // Un rafraîchissement ne renvoie pas de nouveau refresh_token :
            // on conserve celui d'origine, sinon le compte devient orphelin.
            $token->json() + ['refresh_token' => $account->refresh_token],
            $account,
        );
    }

    public function fetchPosts(SocialAccount $account, array $externalIds): Collection
    {
        if ($externalIds === []) {
            return collect();
        }

        $response = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet,statistics,contentDetails',
                'id' => implode(',', $externalIds),
                'maxResults' => $this->batchSize(),
            ]);

        if ($response->failed()) {
            throw SocialProviderFailed::fetchFailed($this->platform(), $response->status(), $response->body());
        }

        return collect($response->json('items', []))
            ->mapWithKeys(fn (array $item) => [
                $item['id'] => new PostMetrics(
                    externalPostId: $item['id'],
                    views: (int) data_get($item, 'statistics.viewCount', 0),
                    caption: trim(
                        data_get($item, 'snippet.title', '').' '.data_get($item, 'snippet.description', '')
                    ),
                    durationSeconds: $this->parseDuration(data_get($item, 'contentDetails.duration')),
                    postedAt: ($published = data_get($item, 'snippet.publishedAt')) ? Carbon::parse($published) : null,
                    ownerExternalId: data_get($item, 'snippet.channelId'),
                ),
            ]);
    }

    public function batchSize(): int
    {
        // videos.list accepte cinquante identifiants pour une seule unité de
        // quota : synchroniser 500 clips coûte 10 unités sur 10 000, pas 500.
        return 50;
    }

    public function quotaCostPerCall(): int
    {
        return 1;
    }

    public function dailyQuota(): ?int
    {
        return (int) config('services.youtube.daily_quota', 10_000);
    }

    /** @param  array<string, mixed>  $token */
    protected function accountFrom(array $token, ?SocialAccount $existing = null): ConnectedAccount
    {
        $channel = Http::withToken($token['access_token'])
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet,statistics',
                'mine' => 'true',
            ]);

        if ($channel->failed()) {
            throw SocialProviderFailed::fetchFailed($this->platform(), $channel->status(), $channel->body());
        }

        $item = $channel->json('items.0');

        if (! $item) {
            throw SocialProviderFailed::noChannel($this->platform());
        }

        return new ConnectedAccount(
            platform: $this->platform(),
            externalAccountId: $item['id'],
            handle: data_get($item, 'snippet.customUrl') ?? data_get($item, 'snippet.title'),
            accessToken: $token['access_token'],
            refreshToken: $token['refresh_token'] ?? $existing?->refresh_token,
            expiresAt: now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            scopes: explode(' ', (string) ($token['scope'] ?? '')),
            followersCount: (int) data_get($item, 'statistics.subscriberCount', 0),
        );
    }

    /** Durée ISO 8601 (« PT1M13S ») en secondes. */
    protected function parseDuration(?string $iso): ?int
    {
        if (blank($iso)) {
            return null;
        }

        try {
            return (int) CarbonInterval::make($iso)?->totalSeconds;
        } catch (\Throwable) {
            return null;
        }
    }
}
