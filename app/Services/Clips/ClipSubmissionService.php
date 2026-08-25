<?php

namespace App\Services\Clips;

use App\Contracts\CampaignBudgetService;
use App\Enums\ClipStatus;
use App\Enums\ParticipationStatus;
use App\Exceptions\ClipSubmissionRefused;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Soumission d'un lien de clip par un clippeur.
 *
 * Le clip naît toujours en attente de modération : la conformité automatique
 * (phase suivante) produit un rapport, jamais une validation. Un hashtag
 * correct ne dit rien du respect réel du brief.
 */
class ClipSubmissionService
{
    public function __construct(
        protected ClipUrlParser $parser,
        protected CampaignBudgetService $budget,
    ) {}

    /**
     * @throws ClipSubmissionRefused
     */
    public function submit(Campaign $campaign, User $clipper, string $url): Clip
    {
        $parsed = $this->parser->parse($url);

        if (! $this->budget->acceptsNewClips($campaign)) {
            throw ClipSubmissionRefused::campaignClosed();
        }

        $participation = $campaign->participations()
            ->where('user_id', $clipper->getKey())
            ->whereIn('status', [ParticipationStatus::Pending, ParticipationStatus::Approved])
            ->with('socialAccount')
            ->get()
            ->first(fn ($p) => $p->socialAccount?->platform === $parsed->platform);

        if (! $participation) {
            // Soit il n'a pas rejoint, soit il a rejoint avec un compte d'une
            // autre plateforme : le message doit distinguer les deux.
            $anyParticipation = $campaign->participations()
                ->where('user_id', $clipper->getKey())
                ->with('socialAccount')
                ->first();

            throw $anyParticipation && $anyParticipation->socialAccount
                ? ClipSubmissionRefused::platformMismatch($parsed->platform, $anyParticipation->socialAccount->platform)
                : ClipSubmissionRefused::noParticipation();
        }

        if ($participation->status !== ParticipationStatus::Approved) {
            throw ClipSubmissionRefused::participationNotApproved();
        }

        try {
            // Rechargé après insertion : `paid_views` et `earned_cents` ne sont
            // pas assignables — seul le moteur de budget les écrit — donc le
            // modèle en mémoire ignorerait leurs valeurs par défaut.
            return DB::transaction(fn () => Clip::create([
                'campaign_id' => $campaign->getKey(),
                'participation_id' => $participation->getKey(),
                'user_id' => $clipper->getKey(),
                'social_account_id' => $participation->social_account_id,
                'platform' => $parsed->platform,
                'external_post_id' => $parsed->externalPostId,
                'url' => $parsed->canonicalUrl,
                'submitted_at' => now(),
                'status' => ClipStatus::PendingReview,
                'compliance_status' => 'pending',
                'views_total' => 0,
            ])->refresh());
        } catch (QueryException $exception) {
            // L'unicité (platform, external_post_id) couvre aussi le cas où un
            // autre clippeur a déjà soumis le même post.
            if (Clip::where('platform', $parsed->platform)
                ->where('external_post_id', $parsed->externalPostId)
                ->exists()) {
                throw ClipSubmissionRefused::alreadySubmitted();
            }

            throw $exception;
        }
    }
}
