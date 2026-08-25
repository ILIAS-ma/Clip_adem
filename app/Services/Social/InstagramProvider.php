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
 * Meta Graph API pour Instagram.
 *
 * Le point de friction n'est pas technique mais côté clippeur : l'API n'expose
 * que les comptes Business ou Creator rattachés à une Page Facebook. Un compte
 * personnel ne peut pas être lié, et c'est la cause d'abandon la plus fréquente
 * — d'où le message d'erreur explicite plus bas plutôt qu'un échec silencieux.
 *
 * Non encore éprouvée sur l'API réelle.
 */
class InstagramProvider implements SocialProvider
{
    protected const VERSION = 'v21.0';

    public function platform(): Platform
    {
        return Platform::Instagram;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.instagram.app_id'))
            && filled(config('services.instagram.app_secret'));
    }

    public function redirectUrl(string $state): string
    {
        return 'https://www.facebook.com/'.self::VERSION.'/dialog/oauth?'.http_build_query([
            'client_id' => config('services.instagram.app_id'),
            'redirect_uri' => route('social.callback', ['platform' => $this->platform()->value]),
            'response_type' => 'code',
            'scope' => 'instagram_basic,instagram_manage_insights,pages_show_list,pages_read_engagement',
            'state' => $state,
        ]);
    }

    public function connect(string $code): ConnectedAccount
    {
        $token = Http::acceptJson()->get(
            'https://graph.facebook.com/'.self::VERSION.'/oauth/access_token',
            [
                'client_id' => config('services.instagram.app_id'),
                'client_secret' => config('services.instagram.app_secret'),
                'redirect_uri' => route('social.callback', ['platform' => $this->platform()->value]),
                'code' => $code,
            ],
        );

        if ($token->failed()) {
            throw SocialProviderFailed::tokenExchange($this->platform(), $token->status(), $token->body());
        }

        return $this->accountFrom($this->exchangeForLongLivedToken($token->json('access_token')));
    }

    /**
     * Meta ne fournit pas de refresh_token : on prolonge le jeton existant.
     * Un jeton longue durée vaut soixante jours, d'où le rafraîchissement
     * planifié bien avant l'échéance.
     */
    public function refresh(SocialAccount $account): ConnectedAccount
    {
        return $this->accountFrom($this->exchangeForLongLivedToken($account->access_token), $account);
    }

    public function fetchPosts(SocialAccount $account, array $externalIds): Collection
    {
        if ($externalIds === []) {
            return collect();
        }

        // Le paramètre `ids` de Graph interroge plusieurs objets en un appel.
        $response = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://graph.facebook.com/'.self::VERSION.'/', [
                'ids' => implode(',', $externalIds),
                'fields' => 'id,caption,media_type,timestamp,permalink,insights.metric(plays,reach)',
            ]);

        if ($response->failed()) {
            throw SocialProviderFailed::fetchFailed($this->platform(), $response->status(), $response->body());
        }

        return collect($response->json() ?? [])
            ->filter(fn ($media) => is_array($media) && isset($media['id']))
            ->mapWithKeys(fn (array $media) => [
                (string) $media['id'] => new PostMetrics(
                    externalPostId: (string) $media['id'],
                    views: $this->readPlays($media),
                    caption: $media['caption'] ?? null,
                    durationSeconds: null, // Non exposé par Graph pour les Reels.
                    postedAt: isset($media['timestamp']) ? Carbon::parse($media['timestamp']) : null,
                    ownerExternalId: $account->external_account_id,
                ),
            ]);
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
        // Meta plafonne par fenêtre glissante et non par jour : la limite se
        // lit dans l'en-tête X-App-Usage, pas dans un compteur local.
        return null;
    }

    protected function exchangeForLongLivedToken(string $shortLived): string
    {
        $response = Http::acceptJson()->get(
            'https://graph.facebook.com/'.self::VERSION.'/oauth/access_token',
            [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.instagram.app_id'),
                'client_secret' => config('services.instagram.app_secret'),
                'fb_exchange_token' => $shortLived,
            ],
        );

        if ($response->failed()) {
            throw SocialProviderFailed::refreshFailed($this->platform(), $response->status(), $response->body());
        }

        return $response->json('access_token');
    }

    protected function accountFrom(string $accessToken, ?SocialAccount $existing = null): ConnectedAccount
    {
        $pages = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://graph.facebook.com/'.self::VERSION.'/me/accounts', [
                'fields' => 'instagram_business_account{id,username,followers_count}',
            ]);

        if ($pages->failed()) {
            throw SocialProviderFailed::fetchFailed($this->platform(), $pages->status(), $pages->body());
        }

        $account = collect($pages->json('data', []))
            ->pluck('instagram_business_account')
            ->filter()
            ->first();

        if (! $account) {
            throw new SocialProviderFailed(
                'Aucun compte Instagram professionnel trouvé. Votre compte doit être en mode Business ou Creator '
                .'et rattaché à une Page Facebook pour que ses statistiques soient lisibles.'
            );
        }

        return new ConnectedAccount(
            platform: $this->platform(),
            externalAccountId: (string) $account['id'],
            handle: $account['username'] ?? null,
            accessToken: $accessToken,
            refreshToken: null,
            expiresAt: now()->addDays(60),
            scopes: ['instagram_basic', 'instagram_manage_insights'],
            followersCount: isset($account['followers_count']) ? (int) $account['followers_count'] : null,
        );
    }

    /** @param  array<string, mixed>  $media */
    protected function readPlays(array $media): int
    {
        return (int) collect(data_get($media, 'insights.data', []))
            ->firstWhere('name', 'plays')['values'][0]['value'] ?? 0;
    }
}
