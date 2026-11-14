<?php

namespace App\Services\Campaigns;

use App\Contracts\ListsRecentPosts;
use App\Enums\ContestMetric;
use App\Enums\Platform;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\SocialProviderManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Le classement tel qu'il serait si les vidéos non soumises comptaient aussi.
 *
 * Ce n'est PAS le classement de la campagne : une vidéo qui n'a pas été soumise ne compte
 * pour personne. C'est un outil de lecture réservé au super-admin, pour savoir qui peut
 * encore passer devant en soumettant ses vidéos avant la fin.
 *
 * Lire les comptes sur TikTok prend du temps : le calcul tourne en tâche planifiée et son
 * résultat est gardé en cache (des tableaux de valeurs simples, rien d'autre).
 */
class ContestShadowStandings
{
    /** Pages de vingt vidéos lues par compte au plus. */
    public const MAX_PAGES = 8;

    public function __construct(
        protected SocialProviderManager $providers,
        protected ContestService $contest,
    ) {}

    public static function cacheKey(Campaign $campaign): string
    {
        return 'contest-shadow-standings:'.$campaign->getKey();
    }

    /** @return array<string, mixed>|null */
    public static function cached(Campaign $campaign): ?array
    {
        return Cache::get(static::cacheKey($campaign));
    }

    /**
     * Calcule le classement complet et le garde en cache.
     *
     * @return array<string, mixed>
     */
    public function refresh(Campaign $campaign): array
    {
        $result = $this->compute($campaign);

        Cache::put(static::cacheKey($campaign), $result, now()->addHours(6));

        return $result;
    }

    /** @return array<string, mixed> */
    public function compute(Campaign $campaign): array
    {
        $people = [];
        $blank = ['name' => '', 'views' => 0, 'clips' => 0, 'posts' => 0, 'un_views' => 0, 'un_clips' => 0, 'un_posts' => 0];

        // Ce qui compte déjà, tel que le site le compte : la même source que le classement officiel.
        foreach ($this->contest->standings($campaign, null, ContestMetric::Views) as $row) {
            $people[$row->user_id] = array_merge($blank, ['name' => $row->name, 'views' => (int) $row->views, 'clips' => (int) $row->clips]);
        }

        foreach ($this->contest->standings($campaign, null, ContestMetric::Posts) as $row) {
            $people[$row->user_id] ??= array_merge($blank, ['name' => $row->name, 'views' => (int) $row->views, 'clips' => (int) $row->clips]);
            $people[$row->user_id]['posts'] = (int) $row->score;
        }

        // Ce qui ne compte pas encore : les vidéos de la campagne que leur auteur n'a pas soumises.
        $min = $campaign->postMinViews();
        $unreadable = 0;

        foreach ($this->unsubmitted($campaign, $unreadable) as $userId => $videos) {
            $people[$userId] ??= array_merge($blank, ['name' => User::find($userId)?->name ?? '?']);
            $people[$userId]['un_clips'] = count($videos);
            $people[$userId]['un_views'] = array_sum(array_column($videos, 'views'));
            $people[$userId]['un_posts'] = count(array_filter($videos, fn (array $video) => $video['views'] >= $min));
        }

        $viewsBoard = $this->board($campaign, ContestMetric::Views, $people);
        $postsBoard = $this->board($campaign, ContestMetric::Posts, $people);

        return [
            'computed_at' => now()->toIso8601String(),
            'unreadable' => $unreadable,
            'views' => $viewsBoard,
            'posts' => $postsBoard,
            'gains' => $this->gains($viewsBoard, $postsBoard),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $people
     * @return array<int, array<string, mixed>>
     */
    protected function board(Campaign $campaign, ContestMetric $metric, array $people): array
    {
        $rewards = $campaign->rewards($metric);

        $rows = collect($people)
            ->map(fn (array $p, int $userId) => $p + [
                'user_id' => $userId,
                'total_views' => $p['views'] + $p['un_views'],
                'total_posts' => $p['posts'] + $p['un_posts'],
            ])
            ->when($metric === ContestMetric::Posts, fn ($c) => $c->filter(fn (array $p) => $p['total_posts'] > 0))
            ->sortByDesc(fn (array $p) => $metric === ContestMetric::Posts
                ? [$p['total_posts'], $p['total_views']]
                : [$p['total_views'], $p['total_posts']])
            ->values();

        return $rows->map(function (array $p, int $index) use ($rewards) {
            $reward = $rewards[$index] ?? null;

            return $p + [
                'rank' => $index + 1,
                'reward' => $reward?->display(),
                'reward_cents' => $reward && $reward->isMoney() ? $reward->amountCents : 0,
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $viewsBoard
     * @param  array<int, array<string, mixed>>  $postsBoard
     * @return array<int, array{user_id: int, name: string, cents: int, parts: array<int, string>}>
     */
    protected function gains(array $viewsBoard, array $postsBoard): array
    {
        $gains = [];

        foreach (['vues' => $viewsBoard, 'clips' => $postsBoard] as $label => $board) {
            foreach ($board as $row) {
                if ($row['reward'] === null) {
                    continue;
                }

                $gains[$row['user_id']] ??= ['user_id' => $row['user_id'], 'name' => $row['name'], 'cents' => 0, 'parts' => []];
                $gains[$row['user_id']]['cents'] += $row['reward_cents'];
                $gains[$row['user_id']]['parts'][] = $label.' : '.$row['reward'];
            }
        }

        usort($gains, fn (array $a, array $b) => $b['cents'] <=> $a['cents']);

        return array_values($gains);
    }

    /**
     * Les vidéos de la campagne, publiées pendant la campagne, que personne n'a soumises.
     *
     * @return array<int, array<int, array{id: string, views: int}>> par clippeur
     */
    protected function unsubmitted(Campaign $campaign, int &$unreadable): array
    {
        $provider = $this->providers->for(Platform::TikTok);

        if (! $provider instanceof ListsRecentPosts) {
            return [];
        }

        $submitted = Clip::query()->pluck('external_post_id')->flip();
        $userIds = CampaignParticipation::where('campaign_id', $campaign->getKey())->pluck('user_id');
        $banned = User::whereIn('id', $userIds)->where('is_banned', true)->pluck('id')->flip();
        $tokens = $this->tokens($campaign);
        $end = $campaign->ends_at ?? now();
        $found = [];

        foreach (SocialAccount::whereIn('user_id', $userIds)->where('platform', Platform::TikTok)->get() as $account) {
            if (isset($banned[$account->user_id])) {
                continue;
            }

            try {
                $cursor = null;

                for ($page = 0; $page < self::MAX_PAGES; $page++) {
                    $recent = $provider->recentPosts($account, 20, $cursor);
                    $oldest = null;

                    foreach ($recent->posts as $post) {
                        $oldest = $post->postedAt && ($oldest === null || $post->postedAt->lt($oldest)) ? $post->postedAt : $oldest;

                        if (isset($submitted[$post->externalPostId])
                            || ! $post->postedAt
                            || $post->postedAt->lt($campaign->starts_at)
                            || $post->postedAt->gt($end)
                            || ! $this->matches((string) $post->caption, $tokens, $campaign)) {
                            continue;
                        }

                        $found[$account->user_id][] = ['id' => $post->externalPostId, 'views' => (int) $post->views];
                    }

                    // Plus ancien que la campagne : inutile de remonter davantage.
                    if ($recent->nextCursor === null || ($oldest !== null && $oldest->lt($campaign->starts_at))) {
                        break;
                    }

                    $cursor = $recent->nextCursor;
                }
            } catch (\Throwable $exception) {
                $unreadable++;
                Log::warning('Classement complet : compte illisible', ['account_id' => $account->getKey(), 'error' => $exception->getMessage()]);
            }
        }

        return $found;
    }

    /**
     * Ce qui désigne une vidéo de la campagne : ses hashtags obligatoires, tous ensemble ; à défaut,
     * les mots du titre (« DJIBRIL94 x LIGUE1 » donne djibril94 et ligue1), n'importe lequel.
     *
     * @return array<int, string>
     */
    protected function tokens(Campaign $campaign): array
    {
        $required = collect($campaign->required_hashtags ?? [])->map(fn ($tag) => mb_strtolower(trim((string) $tag)))->filter()->values();

        if ($required->isNotEmpty()) {
            return $required->all();
        }

        return collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($campaign->title)))
            ->filter(fn ($word) => mb_strlen($word) >= 5)
            ->values()
            ->all();
    }

    protected function matches(string $caption, array $tokens, Campaign $campaign): bool
    {
        if ($tokens === []) {
            return false;
        }

        $caption = mb_strtolower($caption);
        $hasRequired = ! empty($campaign->required_hashtags);

        return $hasRequired
            ? collect($tokens)->every(fn ($token) => str_contains($caption, $token))
            : collect($tokens)->contains(fn ($token) => str_contains($caption, $token));
    }
}
