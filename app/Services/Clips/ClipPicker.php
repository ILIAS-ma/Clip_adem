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
 * sans celles déjà soumises.
 */
class ClipPicker
{
    /** Le temps de choisir, pas assez pour rater une vidéo qu'on vient de poster. */
    protected const CACHE_SECONDS = 120;

    /** Vidéos ajoutées à chaque « Voir plus ». */
    public const PER_PAGE = 12;

    /**
     * Pages lues au plus, de vingt vidéos chacune : un historique entier, sans
     * laisser un compte de mille vidéos vider le quota d'API en un toucher.
     */
    protected const MAX_PAGES = 15;

    public function __construct(
        protected SocialProviderManager $providers,
    ) {}

    /**
     * Les `$want` vidéos les plus récentes pas encore soumises, par compte.
     *
     * On remonte les pages jusqu'à en avoir assez : se contenter de la
     * première rendait la liste vide dès que ses vidéos avaient toutes été
     * soumises, alors que le compte en avait des dizaines d'autres.
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

            [$videos, $hasMore, $error] = $this->available($provider, $account, $want);

            $groups[] = [
                'account_id' => $account->getKey(),
                'platform' => $account->platform->value,
                'handle' => $account->handle,
                'videos' => $videos,
                'has_more' => $hasMore,
                'error' => $error,
            ];
        }

        return $groups;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: bool, 2: bool} vidéos, s'il en reste, erreur
     */
    protected function available(ListsRecentPosts $provider, SocialAccount $account, int $want): array
    {
        $videos = [];
        $cursor = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            try {
                [$batch, $cursor] = $this->page($provider, $account, $cursor);
            } catch (\Throwable $exception) {
                // La liste est un confort : si la plateforme répond mal, le
                // collage du lien reste là, et rien ne doit planter la page.
                Log::info('Liste des vidéos récentes indisponible', [
                    'social_account_id' => $account->getKey(),
                    'error' => $exception->getMessage(),
                ]);

                return [$videos, false, $videos === []];
            }

            // Déjà soumises — par lui ou par un autre : les proposer pour les
            // refuser ensuite ne ferait que tromper.
            $taken = Clip::where('platform', $account->platform)
                ->whereIn('external_post_id', array_column($batch, 'id'))
                ->pluck('external_post_id')
                ->all();

            foreach ($batch as $video) {
                if (! in_array($video['id'], $taken, true)) {
                    $videos[] = $video;
                }
            }

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
