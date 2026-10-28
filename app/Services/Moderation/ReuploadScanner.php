<?php

namespace App\Services\Moderation;

use App\Contracts\ListsRecentPosts;
use App\Enums\ModerationAction;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\SocialAccount;
use App\Services\Social\SocialProviderManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Compare les clips d'un compte à l'historique de ses vidéos, et signale les reprises probables.
 *
 * Une lecture par compte, pas par clip : on liste ses vidéos (durée et légende comprises),
 * puis chaque clip est confronté à celles qui le précèdent. Le résultat est une ligne du
 * journal de modération — le clip reste validé, c'est l'équipe qui tranche.
 */
class ReuploadScanner
{
    /** Pages de vingt vidéos lues par compte au plus. */
    public const MAX_PAGES = 8;

    public function __construct(
        protected SocialProviderManager $providers,
        protected ReuploadDetector $detector,
    ) {}

    /**
     * @param  Collection<int, Clip>  $clips  les clips de ce compte à contrôler
     * @return array{checked: int, flagged: Collection<int, array{clip: Clip, original: array, similarity: float}>}|null null si la lecture a échoué
     */
    public function scan(SocialAccount $account, Collection $clips): ?array
    {
        $history = $this->history($account);

        if ($history === null) {
            return null;
        }

        $byId = collect($history)->keyBy('id');
        $flagged = collect();

        foreach ($clips as $clip) {
            $post = $byId->get($clip->external_post_id);

            $match = $post ? $this->detector->findOriginal($post, $history) : null;

            if ($match && ! $this->alreadyFlagged($clip)) {
                ModerationLog::record(
                    ModerationAction::ClipSuspectedReupload,
                    $clip,
                    null,
                    sprintf(
                        'Même durée (%d s) et légende identique à %d %% à une vidéo publiée le %s.',
                        $post['duration'],
                        (int) round($match['similarity']),
                        $match['post']['posted_at']->timezone(config('app.timezone'))->format('d/m/Y à H\hi'),
                    ),
                    ['original_post_id' => $match['post']['id'], 'similarity' => $match['similarity']],
                );

                $flagged->push(['clip' => $clip, 'original' => $match['post'], 'similarity' => $match['similarity']]);
            }

            // Contrôlé une fois : sans cette marque, chaque passage relirait le compte pour rien.
            $clip->forceFill(['compliance' => array_merge($clip->compliance ?? [], [
                'reupload_checked_at' => now()->toIso8601String(),
            ])])->save();
        }

        return ['checked' => $clips->count(), 'flagged' => $flagged];
    }

    protected function alreadyFlagged(Clip $clip): bool
    {
        return ModerationLog::where('subject_type', $clip->getMorphClass())
            ->where('subject_id', $clip->getKey())
            ->where('action', ModerationAction::ClipSuspectedReupload)
            ->exists();
    }

    /**
     * Les vidéos du compte avec leur durée, ou null si TikTok ne répond pas.
     *
     * @return array<int, array{id: string, caption: ?string, duration: ?int, posted_at: ?CarbonInterface}>|null
     */
    protected function history(SocialAccount $account): ?array
    {
        $provider = $this->providers->for($account->platform);

        if (! $provider instanceof ListsRecentPosts) {
            return null;
        }

        try {
            $ids = [];
            $cursor = null;

            for ($page = 0; $page < self::MAX_PAGES; $page++) {
                $recent = $provider->recentPosts($account, 20, $cursor);

                foreach ($recent->posts as $post) {
                    $ids[] = $post->externalPostId;
                }

                $cursor = $recent->nextCursor;

                if ($cursor === null) {
                    break;
                }
            }

            $history = [];

            foreach (array_chunk($ids, max(1, min(20, $provider->batchSize()))) as $chunk) {
                foreach ($provider->fetchPosts($account, $chunk) as $metrics) {
                    $history[] = [
                        'id' => $metrics->externalPostId,
                        'caption' => $metrics->caption,
                        'duration' => $metrics->durationSeconds,
                        'posted_at' => $metrics->postedAt,
                    ];
                }
            }

            return $history;
        } catch (\Throwable $exception) {
            Log::warning('Contrôle des reprises impossible', ['account_id' => $account->getKey(), 'error' => $exception->getMessage()]);

            return null;
        }
    }
}
