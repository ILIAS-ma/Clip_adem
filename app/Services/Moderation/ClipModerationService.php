<?php

namespace App\Services\Moderation;

use App\Contracts\CampaignBudgetService;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\ParticipationStatus;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Décisions de modération sur les clips et les clippeurs.
 *
 * Le volet budgétaire n'est jamais traité ici : invalider un clip délègue le
 * remboursement à CampaignBudgetService, seul autorisé à écrire sur les
 * compteurs d'argent.
 */
class ClipModerationService
{
    public function __construct(
        protected CampaignBudgetService $budget,
    ) {}

    public function approve(Clip $clip, ?User $by = null): Clip
    {
        return DB::transaction(function () use ($clip, $by) {
            $clip->forceFill([
                'status' => ClipStatus::Approved,
                'rejection_reason' => null,
            ])->save();

            ModerationLog::record(ModerationAction::ClipApproved, $clip, $by);

            return $clip;
        });
    }

    /**
     * Refus avant tout paiement : le clip n'a rien coûté, il n'y a rien à
     * rembourser. Pour un clip déjà crédité, c'est invalidate().
     */
    public function reject(Clip $clip, string $reason, ?User $by = null): Clip
    {
        return DB::transaction(function () use ($clip, $reason, $by) {
            $clip->forceFill([
                'status' => ClipStatus::Rejected,
                'rejection_reason' => $reason,
            ])->save();

            ModerationLog::record(ModerationAction::ClipRejected, $clip, $by, $reason);

            return $clip;
        });
    }

    /**
     * Invalidation d'un clip frauduleux : le budget consommé est rendu à la
     * campagne, qui peut en ressortir de l'état « Épuisée ».
     *
     * Les gains du clippeur baissent d'autant. Un versement PayPal déjà parti
     * n'est pas rattrapé — d'où l'intérêt de modérer avant de payer.
     */
    public function invalidate(Clip $clip, string $reason, ?User $by = null): Clip
    {
        return DB::transaction(function () use ($clip, $reason, $by) {
            $reversal = $this->budget->reverseClip($clip, $reason, $by);

            $clip->refresh()->forceFill([
                'status' => ClipStatus::Invalidated,
                'rejection_reason' => $reason,
            ])->save();

            ModerationLog::record(ModerationAction::ClipInvalidated, $clip, $by, $reason, [
                'refunded_cents' => $reversal->refundedCents,
                'refunded_views' => $reversal->refundedViews,
                'campaign_reactivated' => $reversal->campaignReactivated,
            ]);

            return $clip;
        });
    }

    /**
     * Bannissement d'un clippeur.
     *
     * Gèle systématiquement ses retraits en attente : laisser partir un
     * virement vers un compte que l'on vient de bannir n'aurait pas de sens.
     * L'invalidation de ses clips reste un choix, parce qu'un bannissement
     * pour non-respect du brief ne remet pas forcément en cause les vues déjà
     * générées.
     */
    public function banClipper(User $clipper, string $reason, ?User $by = null, bool $invalidateClips = false): User
    {
        return DB::transaction(function () use ($clipper, $reason, $by, $invalidateClips) {
            $clipper->forceFill([
                'is_banned' => true,
                'banned_at' => now(),
                'ban_reason' => $reason,
            ])->save();

            $clipper->participations()->update(['status' => ParticipationStatus::Banned]);

            $frozen = Payout::where('user_id', $clipper->getKey())
                ->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])
                ->update([
                    'status' => PayoutStatus::Cancelled,
                    'failure_reason' => 'Clippeur banni : '.$reason,
                ]);

            $refunded = 0;

            if ($invalidateClips) {
                $clips = Clip::where('user_id', $clipper->getKey())
                    ->where('status', ClipStatus::Approved)
                    ->get();

                foreach ($clips as $clip) {
                    $refunded += $clip->earned_cents;
                    $this->invalidate($clip, 'Clippeur banni : '.$reason, $by);
                }
            }

            ModerationLog::record(ModerationAction::ClipperBanned, $clipper, $by, $reason, [
                'frozen_payouts' => $frozen,
                'invalidated_clips' => $invalidateClips,
                'refunded_cents' => $refunded,
            ]);

            return $clipper;
        });
    }

    /**
     * Débannissement. Ne ressuscite ni les clips invalidés ni les retraits
     * annulés : ces décisions ont leur propre trace et se reprennent une par
     * une, sciemment.
     */
    public function unbanClipper(User $clipper, ?User $by = null): User
    {
        return DB::transaction(function () use ($clipper, $by) {
            $clipper->forceFill([
                'is_banned' => false,
                'banned_at' => null,
                'ban_reason' => null,
            ])->save();

            $clipper->participations()
                ->where('status', ParticipationStatus::Banned)
                ->update(['status' => ParticipationStatus::Pending]);

            ModerationLog::record(ModerationAction::ClipperUnbanned, $clipper, $by);

            return $clipper;
        });
    }

    /** @return Builder<User> */
    public static function clippersQuery()
    {
        return User::query()->where('role', UserRole::Clipper);
    }
}
