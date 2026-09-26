<?php

namespace Tests\Feature\Moderation;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clips\ClipComplianceChecker;
use App\Services\Moderation\ClipAutoReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La validation automatique des clips.
 *
 * Un clip conforme attendait qu'un humain clique. Sur une plateforme tenue par
 * une personne, cette file devient le goulot de tout le produit : le clippeur
 * publie, ses vues montent, et il n'est pas payé parce que personne n'a ouvert
 * l'administration.
 *
 * La règle est volontairement asymétrique, et ces tests la gardent dans ce
 * sens-là : **seule la certitude approuve**. Se tromper ici ne produit pas un
 * mauvais affichage, ça paie quelqu'un pour un travail qu'il n'a pas fait — et
 * l'argent parti ne revient qu'au prix d'une invalidation, c'est-à-dire d'un
 * conflit.
 */
class AutoReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $clipper;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        config(['clipping.moderation.auto_approve' => true]);

        $this->clipper = User::factory()->create(['role' => UserRole::Clipper]);

        $this->campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create(['status' => CampaignStatus::Active, 'budget_total_cents' => 100_000]);
    }

    /** Un clip en attente, dont la conformité est celle demandée. */
    protected function clip(string $compliance = ClipComplianceChecker::PASSED, array $attributes = []): Clip
    {
        $account = SocialAccount::factory()->create([
            'user_id' => $this->clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        return Clip::factory()->create(array_merge([
            'campaign_id' => $this->campaign->getKey(),
            'user_id' => $this->clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::PendingReview,
            'compliance_status' => $compliance,
            'compliance' => ['checked_at' => now()->toIso8601String(), 'checks' => []],
        ], $attributes));
    }

    protected function review(Clip $clip): ?string
    {
        return app(ClipAutoReview::class)->review($clip);
    }

    #[Test]
    public function a_compliant_clip_is_approved_without_anyone_clicking(): void
    {
        $clip = $this->clip();

        $this->assertNull($this->review($clip), 'Le clip aurait dû être approuvé.');
        $this->assertSame(ClipStatus::Approved, $clip->fresh()->status);
    }

    #[Test]
    public function the_decision_is_recorded_without_an_author(): void
    {
        /*
         * Le journal doit dire que personne n'a décidé. Attribuer la décision
         * à un administrateur qui dormait rendrait l'audit mensonger — et
         * c'est ce journal qu'on relira le jour d'une contestation.
         */
        $clip = $this->clip();

        $this->review($clip);

        $entree = ModerationLog::where('subject_id', $clip->getKey())
            ->where('action', ModerationAction::ClipApproved)
            ->first();

        $this->assertNotNull($entree, 'La décision n’est pas journalisée.');
        $this->assertNull($entree->user_id);
    }

    #[Test]
    public function a_clip_whose_compliance_failed_waits_for_a_human(): void
    {
        $clip = $this->clip(ClipComplianceChecker::FAILED);

        $this->assertSame('conformité non établie', $this->review($clip));
        $this->assertSame(ClipStatus::PendingReview, $clip->fresh()->status);
    }

    #[Test]
    public function a_clip_never_checked_is_not_approved_by_default(): void
    {
        /*
         * `pending` n'est pas une réussite, c'est une absence d'information :
         * le premier relevé n'a pas encore eu lieu. Les confondre approuverait
         * tout clip jamais vérifié — l'inverse exact du contrôle.
         */
        $clip = $this->clip(ClipComplianceChecker::PENDING);

        $this->assertSame('conformité non établie', $this->review($clip));
        $this->assertSame(ClipStatus::PendingReview, $clip->fresh()->status);
    }

    #[Test]
    public function a_banned_clipper_is_never_approved_automatically(): void
    {
        $this->clipper->forceFill(['is_banned' => true, 'banned_at' => now()])->save();

        $clip = $this->clip();

        $this->assertSame('clippeur banni', $this->review($clip));
        $this->assertSame(ClipStatus::PendingReview, $clip->fresh()->status);
    }

    #[Test]
    public function a_human_decision_is_never_undone(): void
    {
        /*
         * Un clip refusé par quelqu'un ne doit jamais remonter tout seul, même
         * s'il devient conforme : défaire une décision humaine sans qu'elle
         * soit revue serait la pire des trahisons de ce système.
         */
        foreach ([ClipStatus::Rejected, ClipStatus::Invalidated, ClipStatus::Approved] as $statut) {
            $clip = $this->clip(attributes: ['status' => $statut]);

            $this->assertSame('statut non concerné', $this->review($clip));
            $this->assertSame($statut, $clip->fresh()->status);
        }
    }

    #[Test]
    public function the_whole_thing_can_be_switched_off(): void
    {
        // Le jour où l'on veut reprendre la main sur chaque clip, un réglage
        // doit suffire — sans redéploiement et sans toucher au code.
        config(['clipping.moderation.auto_approve' => false]);

        $clip = $this->clip();

        $this->assertSame('automatisation désactivée', $this->review($clip));
        $this->assertSame(ClipStatus::PendingReview, $clip->fresh()->status);
    }
}
