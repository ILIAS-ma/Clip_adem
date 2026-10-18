<?php

namespace App\Services\Budget;

use App\Contracts\CampaignBudgetService;
use App\Enums\ModerationAction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\User;
use App\Notifications\BonusGranted;
use App\Support\Budget\CreditResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Primes garanties : ce qui les déclenche et qui en est prévenu.
 *
 * L'argent, lui, ne passe que par CampaignBudgetService::grantBonus().
 */
class BonusService
{
    public const KIND_LAUNCH = 'launch';

    public const KIND_MANUAL = 'manual';

    public function __construct(
        protected CampaignBudgetService $budget,
    ) {}

    /**
     * Prime de lancement : un montant garanti au premier clip validé de
     * chaque clippeur, pour les N premiers clippeurs de la campagne.
     *
     * Une par clippeur et par campagne, par construction : la clé
     * d'idempotence porte le clippeur, pas le clip. Dix clips validés ne
     * valent donc pas dix primes.
     */
    public function awardLaunchBonus(Clip $clip): ?CreditResult
    {
        $campaign = $clip->campaign()->withTrashed()->first();

        if (! $campaign || ! $campaign->launch_bonus_cents || ! $campaign->launch_bonus_slots) {
            return null;
        }

        /*
         * Une campagne qui exige un volume ne paie rien en dessous : la prime
         * attend que le minimum de clips validés soit atteint. Sans ça, elle
         * rouvrirait exactement le robinet que la règle ferme. Comme elle est
         * retentée à chaque validation, elle tombe sur celle qui atteint le
         * compte.
         */
        if ($campaign->min_clips_per_clipper > 0
            && $campaign->payableClipCountFor($clip->user_id) < $campaign->min_clips_per_clipper) {
            return null;
        }

        $result = $this->budget->grantBonus(
            clip: $clip,
            amountCents: $campaign->launch_bonus_cents,
            idempotencyKey: sprintf('campaign:%d:user:%d:launch-bonus', $campaign->getKey(), $clip->user_id),
            kind: self::KIND_LAUNCH,
            maxOfKind: $campaign->launch_bonus_slots,
            reason: 'Prime de lancement',
        );

        if ($result->creditedCents > 0) {
            $this->tell($clip, $result->creditedCents, 'Prime de lancement sur « '.$campaign->title.' »');
        }

        return $result;
    }

    /** Prime accordée à la main par l'équipe, avec un motif montré au clippeur. */
    public function grantManual(Clip $clip, int $amountCents, string $reason, ?User $by = null): CreditResult
    {
        $result = $this->budget->grantBonus(
            clip: $clip,
            amountCents: $amountCents,
            idempotencyKey: sprintf('clip:%d:bonus:%s', $clip->getKey(), Str::uuid()),
            kind: self::KIND_MANUAL,
            reason: $reason,
            by: $by,
        );

        if ($result->creditedCents > 0) {
            ModerationLog::record(ModerationAction::BonusGranted, $clip, $by, $reason, [
                'amount_cents' => $result->creditedCents,
            ]);

            $this->tell($clip, $result->creditedCents, $reason);
        }

        return $result;
    }

    /**
     * Prix d'un classement, versé sur le meilleur clip du lauréat.
     *
     * Une clé par place et par campagne : relancer le versement après une
     * interruption ne paie jamais deux fois la même place.
     */
    public function grantPrize(Campaign $campaign, int $clipId, int $rank, int $amountCents, ?User $by = null): CreditResult
    {
        $clip = Clip::findOrFail($clipId);
        $reason = ($rank === 1 ? '1re' : $rank.'e').' place du classement « '.$campaign->title.' »';

        $result = $this->budget->grantBonus(
            clip: $clip,
            amountCents: $amountCents,
            idempotencyKey: sprintf('campaign:%d:prize:%d', $campaign->getKey(), $rank),
            kind: CampaignBudgetService::BONUS_KIND_PRIZE,
            reason: $reason,
            by: $by,
        );

        if ($result->creditedCents > 0) {
            ModerationLog::record(ModerationAction::BonusGranted, $clip, $by, $reason, [
                'amount_cents' => $result->creditedCents,
                'rank' => $rank,
            ]);

            $this->tell($clip, $result->creditedCents, $reason);
        }

        return $result;
    }

    protected function tell(Clip $clip, int $cents, string $reason): void
    {
        try {
            $clip->user?->notify(new BonusGranted($clip, $cents, $reason));
        } catch (\Throwable $exception) {
            Log::warning('Notification de prime non envoyée', [
                'clip_id' => $clip->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
