<?php

namespace App\Services\Clippers;

use App\Enums\ContestMetric;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\PrizeClaim;
use App\Models\User;
use App\Services\Campaigns\ContestService;

/**
 * Les prix de classement d'un clippeur, du provisoire au versé.
 *
 * Un prix ne rejoint le solde qu'au moment où l'équipe attribue les récompenses, parfois
 * plusieurs heures après la fin de la campagne : vérification des égalités, des signalements.
 * Entre les deux, le clippeur a pourtant fini sa campagne et sait où il est. Ce service lui
 * montre ce qu'il a gagné sans attendre, en distinguant trois états :
 *
 *  - « live »    : la campagne court encore, la place est provisoire ;
 *  - « pending » : la campagne est finie, le prix est acquis mais pas encore versé ;
 *  - « awarded » : le prix est versé, il est déjà dans le solde et dans les gains validés.
 */
class ClipperPrizes
{
    public const LIVE = 'live';

    public const PENDING = 'pending';

    public const AWARDED = 'awarded';

    public function __construct(
        protected ContestService $contest,
    ) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     credited_cents: int,
     *     pending_cents: int,
     *     live_cents: int,
     *     total_cents: int,
     *     claimable: list<array{campaign_id: int, title: string, cents: int, claimed: bool}>,
     * }
     */
    public function for(User $user): array
    {
        $campaignIds = Clip::where('user_id', $user->getKey())->pluck('campaign_id')->unique();
        $claimed = PrizeClaim::where('user_id', $user->getKey())->pluck('campaign_id')->flip();

        $rows = [];

        foreach (Campaign::whereIn('id', $campaignIds)->get() as $campaign) {
            if (! $campaign->isContest()) {
                continue;
            }

            $state = match (true) {
                $campaign->prizes_paid_at !== null => self::AWARDED,
                $campaign->contestIsOver() => self::PENDING,
                default => self::LIVE,
            };

            foreach (ContestMetric::cases() as $metric) {
                $row = $this->contest->standings($campaign, null, $metric)->firstWhere('user_id', $user->getKey());
                $reward = $row ? $campaign->rewardFor($row->rank, $metric) : null;

                if ($reward === null) {
                    continue;
                }

                $rows[] = [
                    'campaign_id' => $campaign->getKey(),
                    'campaign' => $campaign->title,
                    'board' => $metric->title(),
                    'rank' => $row->rank,
                    'reward' => $reward->display(),
                    'is_money' => $reward->isMoney(),
                    'cents' => $reward->isMoney() ? $reward->amountCents : 0,
                    'state' => $state,
                    'ends_at' => $campaign->ends_at,
                    'claimed' => isset($claimed[$campaign->getKey()]),
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => [$b['ends_at']?->timestamp ?? PHP_INT_MAX, $a['rank']] <=> [$a['ends_at']?->timestamp ?? PHP_INT_MAX, $b['rank']]);

        $sum = fn (string $state) => (int) collect($rows)->where('state', $state)->sum('cents');

        // Déjà versé : ce sont les gains validés, prix attribués compris. On ne les recompte pas.
        $credited = $user->earnedCents();
        $pending = $sum(self::PENDING);

        return [
            'rows' => $rows,
            'credited_cents' => $credited,
            'pending_cents' => $pending,
            'live_cents' => $sum(self::LIVE),
            'total_cents' => $credited + $pending,
            'claimable' => collect($rows)->where('state', self::PENDING)->groupBy('campaign_id')
                ->map(fn ($group, $id) => [
                    'campaign_id' => (int) $id,
                    'title' => $group->first()['campaign'],
                    'cents' => (int) $group->sum('cents'),
                    'claimed' => $group->first()['claimed'],
                ])->values()->all(),
        ];
    }

    /** Ce clippeur a-t-il un prix acquis, pas encore versé, sur cette campagne ? */
    public function hasPending(User $user, Campaign $campaign): bool
    {
        return collect($this->for($user)['claimable'])->contains(fn (array $c) => $c['campaign_id'] === $campaign->getKey());
    }
}
