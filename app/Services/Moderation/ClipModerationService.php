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
use App\Notifications\ClipRejected;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    /**
     * Prévient le clippeur sans jamais faire échouer la décision.
     *
     * L'envoi est mis en file, mais même la mise en file peut échouer — base
     * de file injoignable, sérialisation. Une invalidation qui rend le budget
     * à la campagne ne doit pas être annulée parce qu'un e-mail n'est pas
     * parti : la trace de modération, elle, est déjà écrite.
     */
    protected function tellTheClipper(Clip $clip, string $reason, bool $wasPaid): void
    {
        try {
            $clip->user?->notify(new ClipRejected($clip, $reason, $wasPaid));
        } catch (\Throwable $exception) {
            Log::warning('Notification de refus non envoyée', [
                'clip_id' => $clip->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

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

            $this->tellTheClipper($clip, $reason, wasPaid: false);

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

            // `wasPaid` vient du remboursement réel, pas du statut : c'est lui
            // qui dit si le solde du clippeur vient de baisser, et donc s'il
            // faut le prévenir avant qu'il le constate tout seul.
            $this->tellTheClipper($clip, $reason, wasPaid: $reversal->refundedCents > 0);

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
