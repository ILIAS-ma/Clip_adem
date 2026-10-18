<?php

namespace App\Services\Clips;

use App\Contracts\ListsRecentPosts;
use App\Enums\ParticipationStatus;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\SocialProviderManager;
use App\Support\Social\RecentPost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Les vidéos récentes qu'un clippeur peut soumettre d'un toucher.
 *
 * Seulement depuis les comptes avec lesquels il participe à la campagne, et
 * sans celles déjà soumises — par lui ou par un autre : les proposer pour les
 * refuser ensuite ne ferait que tromper.
 */
class ClipPicker
{
    /** Le temps de choisir, pas assez pour rater une vidéo qu'on vient de poster. */
    protected const CACHE_SECONDS = 120;

    public function __construct(
        protected SocialProviderManager $providers,
    ) {}

    /**
     * @return list<array{account_id: int, platform: string, handle: ?string, videos: list<array<string, mixed>>, error: bool}>
     */
    public function forCampaign(Campaign $campaign, User $clipper): array
    {
        $accounts = $campaign->participations()
            ->where('user_id', $clipper->getKey())
            ->where('status', ParticipationStatus::Approved)
            ->with('socialAccount')
            ->get()
            ->pluck('socialAccount')
            ->filter(fn (?SocialAccount $account) => $account && $account->isSyncable())
            ->unique('id');

        $groups = [];

        foreach ($accounts as $account) {
            $provider = $this->providers->for($account->platform);

            if (! $provider instanceof ListsRecentPosts) {
                continue;
            }

            [$videos, $error] = $this->recent($provider, $account);

            $taken = Clip::where('platform', $account->platform)
                ->whereIn('external_post_id', array_column($videos, 'id'))
                ->pluck('external_post_id')
                ->all();

            $groups[] = [
                'account_id' => $account->getKey(),
                'platform' => $account->platform->value,
                'handle' => $account->handle,
                'videos' => array_values(array_filter($videos, fn (array $video) => ! in_array($video['id'], $taken, true))),
                'error' => $error,
            ];
        }

        return $groups;
    }

    /**
     * En cache sous forme de tableaux simples : la configuration refuse de
     * relire des objets depuis le cache.
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    protected function recent(ListsRecentPosts $provider, SocialAccount $account): array
    {
        try {
            $videos = Cache::remember(
                "clip-picker:{$account->getKey()}",
                self::CACHE_SECONDS,
                fn () => $provider->recentPosts($account, 12)->map(fn (RecentPost $post) => $post->toArray())->values()->all(),
            );

            return [$videos, false];
        } catch (\Throwable $exception) {
            // La liste est un confort : si la plateforme répond mal, le
            // collage du lien reste là, et rien ne doit planter la page.
            Log::info('Liste des vidéos récentes indisponible', [
                'social_account_id' => $account->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return [[], true];
        }
    }

    /** Oublie la liste d'un compte, pour qu'une vidéo soumise disparaisse aussitôt. */
    public function forget(SocialAccount $account): void
    {
        Cache::forget("clip-picker:{$account->getKey()}");
    }
}
