<?php

namespace App\Services\Campaigns;

use App\Enums\ClipStatus;
use App\Enums\ContestMetric;
use App\Events\MessagePosted;
use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\ContestRewardWon;
use App\Services\Budget\BonusService;
use App\Support\Budget\CreditOutcome;
use App\Support\Budget\CreditResult;
use App\Support\Campaigns\Reward;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Campagnes « classement » : qui est devant, et le versement des prix.
 *
 * Le classement se lit sur les vues des clips VALIDÉS. Tant que la campagne
 * court, ce sont les vues actuelles ; une fois la date de fin passée, ce sont
 * les vues relevées au plus tard à cette date — sans quoi les relevés qui
 * continuent après la fin feraient encore bouger un podium déjà annoncé.
 */
class ContestService
{
    public function __construct(
        protected BonusService $bonuses,
    ) {}

    /**
     * @return Collection<int, object{rank: int, user_id: int, name: string, views: int, clips: int, score: int, best_clip_id: int}>
     */
    public function standings(Campaign $campaign, ?int $limit = null, ContestMetric $board = ContestMetric::Views): Collection
    {
        $frozenAt = $campaign->ends_at !== null && now()->gt($campaign->ends_at) ? $campaign->ends_at : null;

        /*
         * Classement arrêté : le dernier relevé fait au plus tard à la date de
         * fin. Un clip relevé seulement après la fin compte pour zéro — ses
         * vues sont arrivées trop tard. Un clip jamais relevé du tout garde
         * ses vues actuelles : sans relevé, il n'y a rien d'autre à lire.
         */
        $views = $frozenAt
            ? DB::raw('COALESCE('
                .'(SELECT MAX(s.views) FROM clip_view_snapshots s WHERE s.clip_id = clips.id AND s.captured_at <= '
                .DB::getPdo()->quote($frozenAt->toDateTimeString()).'), '
                .'(SELECT CASE WHEN COUNT(*) = 0 THEN clips.views_total ELSE 0 END FROM clip_view_snapshots s2 WHERE s2.clip_id = clips.id)'
                .') as effective_views')
            : DB::raw('clips.views_total as effective_views');

        $clips = DB::table('clips')
            ->join('users', 'users.id', '=', 'clips.user_id')
            ->where('clips.campaign_id', $campaign->getKey())
            ->where('clips.status', ClipStatus::Approved->value)
            // Un compte banni ne monte pas sur un podium public.
            ->where('users.is_banned', false)
            // Une vidéo publiée hors de la campagne n'y compte pas (voir Campaign::enforcesPostingWindow).
            ->whereRaw(...Campaign::postingWindowSql('clips'))
            // Classement arrêté : un clip soumis après la fin n'entre plus.
            ->when($frozenAt, fn ($query) => $query->where('clips.submitted_at', '<=', $frozenAt))
            ->select('clips.id', 'clips.user_id', 'clips.submitted_at', 'users.name', $views)
            ->get();

        $byPosts = $board === ContestMetric::Posts;
        $threshold = $campaign->postMinViews();

        $rows = $clips
            ->groupBy('user_id')
            ->map(function (Collection $own) use ($byPosts, $threshold) {
                $best = $own->sortByDesc('effective_views')->first();
                $views = (int) $own->sum('effective_views');
                // Au classement des clips, seuls comptent ceux qui ont
                // atteint le seuil de vues fixé par l'admin.
                $qualified = $own->filter(fn ($clip) => (int) $clip->effective_views >= $threshold)->count();

                return (object) [
                    'user_id' => (int) $best->user_id,
                    'name' => $best->name,
                    'views' => $views,
                    'clips' => $byPosts ? $qualified : $own->count(),
                    // Le chiffre qui classe : clips au-dessus du seuil, ou vues.
                    'score' => $byPosts ? $qualified : $views,
                    'best_clip_id' => (int) $best->id,
                    'first_at' => (string) $own->min('submitted_at'),
                ];
            })
            ->filter(fn (object $row) => $row->score > 0)
            /*
             * Départage : au nombre de clips, les vues font la différence ;
             * ensuite, ou directement aux vues, celui qui a publié en premier.
             */
            ->sort(fn (object $a, object $b) => [$b->score, $b->views, $a->first_at] <=> [$a->score, $a->views, $b->first_at])
            ->values()
            ->map(function (object $row, int $index) {
                $row->rank = $index + 1;
                unset($row->first_at);

                return $row;
            });

        return $limit ? $rows->take($limit)->values() : $rows;
    }

    /** La ligne d'un clippeur dans le classement, ou null s'il n'y figure pas. */
    public function rowFor(Campaign $campaign, User $clipper, ContestMetric $board = ContestMetric::Views): ?object
    {
        return $this->standings($campaign, board: $board)->firstWhere('user_id', $clipper->getKey());
    }

    /**
     * Clôture le classement : attribue la récompense de chaque place, une seule fois.
     *
     * - Argent : versé sur le solde par le moteur de budget, avec une clé par
     *   place — une relance après une coupure ne paie jamais deux fois.
     * - Objet : le gagnant est prévenu, et l'équipe lui écrit dans sa
     *   messagerie pour organiser la remise.
     * - Rien : le classement est simplement clôturé, podium conservé.
     *
     * Les places récompensées sans clippeur (moins de participants que de
     * récompenses) ne sont pas attribuées ; leur argent reste au budget.
     *
     * @return list<array{board: ContestMetric, rank: int, user: string, reward: Reward, result: ?CreditResult}>
     */
    public function awardRewards(Campaign $campaign, ?User $by = null): array
    {
        if (! $campaign->isContest()) {
            throw new InvalidArgumentException('Cette campagne n’est pas un classement.');
        }

        if (! $campaign->contestIsOver()) {
            throw new InvalidArgumentException('Le classement n’est pas encore arrêté : attendez la date de fin ou terminez la campagne.');
        }

        if ($campaign->prizes_paid_at !== null) {
            throw new InvalidArgumentException('Les récompenses de ce classement ont déjà été attribuées.');
        }

        $awarded = [];
        $podiums = [];

        // Les deux tableaux, chacun avec ses récompenses : un même clippeur
        // peut gagner les deux, chaque place a sa propre clé.
        foreach (ContestMetric::cases() as $board) {
            $standings = $this->standings($campaign, board: $board);
            $rewards = $campaign->rewards($board);
            // Ce qui a déjà été attribué lors d'une tentative précédente : un
            // lot ne doit pas être annoncé deux fois si l'on relance.
            $already = collect($this->winnersOf($campaign, $board))->keyBy('rank');
            $lines = [];

            foreach ($rewards as $index => $reward) {
                $row = $standings[$index] ?? null;

                if ($row === null) {
                    break;
                }

                $result = null;

                if ($reward->isMoney()) {
                    $result = $this->bonuses->grantPrize($campaign, $row->best_clip_id, $row->rank, $reward->amountCents, $by, $board);
                } elseif (! ($already[$row->rank]['awarded'] ?? false)) {
                    $this->announceItem($campaign, $row, $reward, $by, $board);
                }

                $lines[] = ['board' => $board, 'rank' => $row->rank, 'user' => $row->name, 'reward' => $reward, 'result' => $result];
            }

            /*
             * Podium conservé : les places récompensées, ou à défaut les trois
             * premières d'un tableau sans récompense.
             */
            $podiums[$board->value] = $standings->take(max(count($rewards), 3))->map(function (object $row) use ($campaign, $board, $lines) {
                $line = collect($lines)->firstWhere('rank', $row->rank);

                return [
                    'rank' => $row->rank,
                    'user_id' => $row->user_id,
                    'name' => $row->name,
                    'views' => $row->views,
                    'clips' => $row->clips,
                    'reward' => $campaign->rewardFor($row->rank, $board)?->display(),
                    'awarded' => $line !== null && $this->settled($line['result']),
                ];
            })->values()->all();

            $awarded = [...$awarded, ...$lines];
        }

        /*
         * Clôturé seulement si chaque place est passée : un prix en argent
         * refusé (budget insuffisant) doit pouvoir être retenté, sans repayer
         * les places déjà versées ni réannoncer les lots déjà remis.
         */
        $complete = collect($awarded)->every(fn (array $line) => $this->settled($line['result']));

        $campaign->forceFill([
            'contest_winners' => $podiums,
            'prizes_paid_at' => $complete ? now() : null,
        ])->save();

        return $awarded;
    }

    /**
     * Le podium figé d'un tableau. Les toutes premières clôtures stockaient une
     * simple liste : c'était celui des vues.
     *
     * @return list<array<string, mixed>>
     */
    public function winnersOf(Campaign $campaign, ContestMetric $board): array
    {
        $winners = $campaign->contest_winners ?? [];

        if (array_is_list($winners)) {
            return $board === ContestMetric::Views ? $winners : [];
        }

        return $winners[$board->value] ?? [];
    }

    /** Une place est réglée : lot annoncé, ou argent versé (maintenant ou avant). */
    protected function settled(?CreditResult $result): bool
    {
        return $result === null || $result->wasPaid() || $result->outcome === CreditOutcome::AlreadyProcessed;
    }

    /** Prévient le gagnant d'un lot, et lui écrit au nom de l'équipe. */
    protected function announceItem(Campaign $campaign, object $row, Reward $reward, ?User $by, ContestMetric $board): void
    {
        $winner = User::find($row->user_id);

        if (! $winner) {
            return;
        }

        try {
            $winner->notify(new ContestRewardWon($campaign, $row->rank, (string) $reward->label, $board));
        } catch (\Throwable $exception) {
            Log::warning('Notification de lot non envoyée', ['user_id' => $winner->getKey(), 'error' => $exception->getMessage()]);
        }

        if ($by) {
            $place = $row->rank === 1 ? '1re place' : $row->rank.'e place';

            MessagePosted::dispatch(Conversation::for($winner)->post($by, sprintf(
                'Bravo ! Vous terminez %s du %s de « %s » et gagnez : %s. '
                .'Répondez ici avec vos coordonnées pour qu’on organise la remise.',
                $place,
                $board->boardName(),
                $campaign->title,
                $reward->label,
            )));
        }
    }
}
