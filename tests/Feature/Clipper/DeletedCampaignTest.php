<?php

namespace Tests\Feature\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use App\Support\Budget\CreditOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Une campagne retirée du catalogue.
 *
 * La suppression est douce, et deux exigences se croisent : le clippeur garde
 * l'historique de ce qu'il a publié — y compris ce qu'il a gagné — mais
 * l'argent, lui, s'arrête. Sans la première, la page « Mes clips » tombait en
 * erreur 500 ; sans la seconde, un clip continuerait d'être crédité sur une
 * campagne que plus personne ne surveille.
 */
class DeletedCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected User $clipper;

    protected Campaign $campaign;

    protected Clip $clip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya.clips',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ]);

        $this->campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create([
                'title' => 'Campagne retirée',
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);

        $this->clip = Clip::factory()->create([
            'campaign_id' => $this->campaign->getKey(),
            'user_id' => $this->clipper->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => 50_000,
        ]);
    }

    #[Test]
    public function the_clip_list_survives_the_deletion(): void
    {
        // C'est la panne constatée : une erreur 500 sur toute la page dès
        // qu'une campagne était retirée du catalogue.
        $this->campaign->delete();

        $this->actingAs($this->clipper)
            ->get(route('clips.index'))
            ->assertSuccessful();
    }

    #[Test]
    public function the_clip_page_still_names_the_campaign(): void
    {
        // Le clippeur doit pouvoir dire pour quoi il a travaillé, même des
        // mois après le retrait de la campagne.
        $this->campaign->delete();

        $this->actingAs($this->clipper)
            ->get(route('clips.show', $this->clip))
            ->assertSuccessful()
            ->assertSee('Campagne retirée');
    }

    #[Test]
    public function a_deleted_campaign_stops_paying(): void
    {
        // Sinon un clip continuerait d'être crédité sur un budget que plus
        // personne ne voit ni ne surveille.
        $this->campaign->delete();

        $result = app(CampaignBudgetService::class)->creditViews(
            $this->clip->fresh(),
            50_000,
            'clip:'.$this->clip->id.':apres-suppression',
        );

        $this->assertSame(CreditOutcome::CampaignClosed, $result->outcome);
        $this->assertSame(0, $result->creditedCents);
        $this->assertSame(0, $this->clip->fresh()->earned_cents);
    }

    #[Test]
    public function what_was_earned_before_stays_earned(): void
    {
        // Retirer une campagne ne reprend pas l'argent déjà gagné : ce serait
        // une invalidation, et elle se décide clip par clip.
        app(CampaignBudgetService::class)->creditViews(
            $this->clip,
            50_000,
            'clip:'.$this->clip->id.':avant-suppression',
        );

        $acquis = $this->clip->fresh()->earned_cents;
        $this->assertGreaterThan(0, $acquis);

        $this->campaign->delete();

        $this->assertSame($acquis, $this->clip->fresh()->earned_cents);
        $this->assertSame($acquis, $this->clipper->fresh()->earnedCents());
    }
}
