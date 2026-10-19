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
 * sur tout l'historique du compte, page après page.
 */
class ClipPicker
{
    /** Le temps de choisir, pas assez pour rater une vidéo qu'on vient de poster. */
    protected const CACHE_SECONDS = 120;

    /** Vidéos ajoutées à chaque chargement : un multiple de trois, la grille en a trois par ligne. */
    public const PER_PAGE = 24;

    /**
     * Pages lues au plus, de vingt vidéos chacune : tout l'historique d'un
     * clippeur, sans laisser un compte de dix mille vidéos vider le quota.
     */
    protected const MAX_PAGES = 50;

    public function __construct(
        protected SocialProviderManager $providers,
    ) {}

    /**
     * Les `$want` vidéos les plus récentes de chaque compte, des plus récentes
     * aux plus anciennes. Celles déjà soumises restent dans la liste, marquées :
     * le clippeur voit où il en est, au lieu d'une liste qui rétrécit sans
     * qu'il comprenne pourquoi.
     *
     * @return list<array{account_id: int, platform: string, handle: ?string, videos: list<array<string, mixed>>, has_more: bool, error: bool}>
     */
    public function forCampaign(Campaign $campaign, User $clipper, int $want = self::PER_PAGE): array
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

            [$videos, $hasMore, $error] = $this->collect($provider, $account, $want);

            // Déjà soumises — par lui ou par un autre : montrées, mais plus
            // sélectionnables.
            $taken = Clip::where('platform', $account->platform)
                ->whereIn('external_post_id', array_column($videos, 'id'))
                ->pluck('external_post_id')
                ->all();

            $groups[] = [
                'account_id' => $account->getKey(),
                'platform' => $account->platform->value,
                'handle' => $account->handle,
                'videos' => array_map(fn (array $video) => $video + ['submitted' => in_array($video['id'], $taken, true)], $videos),
                'has_more' => $hasMore,
                'error' => $error,
            ];
        }

        return $groups;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: bool, 2: bool} vidéos, s'il en reste, erreur
     */
    protected function collect(ListsRecentPosts $provider, SocialAccount $account, int $want): array
    {
        $videos = [];
        $cursor = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            try {
                [$batch, $cursor] = $this->page($provider, $account, $cursor);
            } catch (\Throwable $exception) {
                // La liste ne doit jamais planter la page : ce qui a été lu
                // reste affiché, et l'erreur n'est signalée que s'il n'y a rien.
                Log::info('Liste des vidéos récentes indisponible', [
                    'social_account_id' => $account->getKey(),
                    'error' => $exception->getMessage(),
                ]);

                return [$videos, false, $videos === []];
            }

            array_push($videos, ...$batch);

            if (count($videos) > $want) {
                return [array_slice($videos, 0, $want), true, false];
            }

            if ($cursor === null) {
                return [$videos, false, false];
            }
        }

        return [$videos, false, false];
    }

    /**
     * En cache sous forme de tableaux simples : la configuration refuse de
     * relire des objets depuis le cache.
     *
     * @return array{0: list<array<string, mixed>>, 1: ?string} vidéos, curseur suivant
     */
    protected function page(ListsRecentPosts $provider, SocialAccount $account, ?string $cursor): array
    {
        return Cache::remember(
            $this->cacheKey($account, $cursor),
            self::CACHE_SECONDS,
            function () use ($provider, $account, $cursor) {
                $page = $provider->recentPosts($account, 20, $cursor);

                return [$page->posts->map(fn (RecentPost $post) => $post->toArray())->values()->all(), $page->nextCursor];
            },
        );
    }

    protected function cacheKey(SocialAccount $account, ?string $cursor): string
    {
        // La version change à chaque « Actualiser » ou soumission : toutes les
        // pages du compte sont oubliées d'un coup, sans les connaître une à une.
        $version = Cache::get("clip-picker:{$account->getKey()}:v", 0);

        return "clip-picker:{$account->getKey()}:{$version}:".($cursor ?? 'first');
    }

    /** Oublie la liste d'un compte, pour qu'une vidéo soumise disparaisse aussitôt. */
    public function forget(SocialAccount $account): void
    {
        Cache::forever("clip-picker:{$account->getKey()}:v", (int) Cache::get("clip-picker:{$account->getKey()}:v", 0) + 1);
    }
}
