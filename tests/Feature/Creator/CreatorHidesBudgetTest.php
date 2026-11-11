<?php

namespace Tests\Feature\Creator;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un créateur qui suit ses statistiques sans voir l'argent mis pour les clippeurs : ni le budget, ni les
 * prix, ni ce que rapportent les clips. Les autres créateurs voient toujours tout.
 */
class CreatorHidesBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function creatorUser(bool $hideBudget): User
    {
        $user = User::factory()->create(['role' => UserRole::Creator, 'email_verified_at' => now()]);

        Creator::factory()->create(['user_id' => $user->getKey(), 'is_active' => true, 'hide_budget' => $hideBudget]);

        return $user->fresh();
    }

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR', 'paypal_email' => fake()->unique()->safeEmail(),
        ]);
    }

    /** Une campagne payée aux vues, créditée : budget, coût par clip et tarif au millier existent. */
    protected function paidPerViewCampaign(Creator $creator): Campaign
    {
        $campaign = Campaign::factory()->withRate(Platform::TikTok, ratePer1kCents: 100)->create([
            'creator_id' => $creator->getKey(), 'status' => CampaignStatus::Active, 'budget_total_cents' => 100_000,
        ]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(), 'user_id' => $this->clipper()->getKey(), 'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved, 'views_total' => 200_000,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, 200_000, "clip:{$clip->id}:snapshot:1");

        return $campaign->fresh();
    }

    protected function contest(Creator $creator): Campaign
    {
        $campaign = Campaign::factory()->contest([50_000, 20_000])->create([
            'creator_id' => $creator->getKey(), 'status' => CampaignStatus::Active,
            'starts_at' => now()->subDays(3), 'ends_at' => now()->addDays(3),
        ]);

        Clip::factory()->create([
            'campaign_id' => $campaign->getKey(), 'user_id' => $this->clipper()->getKey(), 'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved, 'views_total' => 90_000, 'posted_at' => now()->subDay(), 'submitted_at' => now()->subDay(),
        ]);

        return $campaign;
    }

    // ------------------------------------------------------------------

    #[Test]
    public function the_hidden_creator_sees_the_stats_but_no_money_on_a_paid_per_view_campaign(): void
    {
        $user = $this->creatorUser(hideBudget: true);
        $campaign = $this->paidPerViewCampaign($user->creator);

        $this->actingAs($user)
            ->get(route('creator.campaigns.show', $campaign))
            ->assertSuccessful()
            // Les statistiques restent entières.
            ->assertSee('Vues générées')
            ->assertSee('Clippeurs')
            ->assertSee('Meilleur clip')
            // Aucun montant : ni dépensé, ni coût par clip, ni tarif, ni répartition de la dépense.
            ->assertDontSee('Dépensé')
            ->assertDontSee('Coût')
            ->assertDontSee('/1000')
            ->assertDontSee('Par plateforme')
            ->assertDontSee('1 000,00 €');
    }

    #[Test]
    public function the_hidden_creator_sees_no_prize_on_a_contest(): void
    {
        $user = $this->creatorUser(hideBudget: true);
        $campaign = $this->contest($user->creator);

        $this->actingAs($user)
            ->get(route('creator.campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee('Vues générées')
            ->assertDontSee('Récompenses')
            ->assertDontSee('1re place')
            ->assertDontSee('500 €')
            ->assertDontSee('200 €');
    }

    #[Test]
    public function the_brief_keeps_its_content_but_loses_the_lines_that_announce_money(): void
    {
        // Le brief est du texte libre : il annonce souvent la cagnotte et les prix.
        $brief = "🎯 Le contenu à clipper : les meilleurs moments du live.\n💰 Cagnotte : 250 €\n🏆 Classement VUES (215 €)\n🥇 1er : 80 €\n📋 Règles : une vidéo par jour, hashtag obligatoire.";

        $hidden = $this->creatorUser(hideBudget: true);
        $campaign = $this->contest($hidden->creator);
        $campaign->forceFill(['brief' => $brief])->save();

        $this->actingAs($hidden)
            ->get(route('creator.campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee('Le contenu à clipper')
            ->assertSee('une vidéo par jour')
            ->assertDontSee('Cagnotte')
            ->assertDontSee('250 €')
            ->assertDontSee('80 €');

        // Un créateur qui voit le budget lit le brief tel quel.
        $visible = $this->creatorUser(hideBudget: false);
        $other = $this->contest($visible->creator);
        $other->forceFill(['brief' => $brief])->save();

        $this->actingAs($visible)
            ->get(route('creator.campaigns.show', $other))
            ->assertSuccessful()
            ->assertSee('Cagnotte : 250 €')
            ->assertSee('1er : 80 €');
    }

    #[Test]
    public function another_creator_still_sees_the_budget_and_the_prizes(): void
    {
        $user = $this->creatorUser(hideBudget: false);
        $paid = $this->paidPerViewCampaign($user->creator);
        $contest = $this->contest($user->creator);

        $this->actingAs($user)
            ->get(route('creator.campaigns.show', $paid))
            ->assertSuccessful()
            ->assertSee('Dépensé')
            ->assertSee('Coût')
            ->assertSee('/1000');

        $this->actingAs($user)
            ->get(route('creator.campaigns.show', $contest))
            ->assertSuccessful()
            ->assertSee('Récompenses')
            ->assertSee('1re place');
    }

    #[Test]
    public function the_empty_dashboard_does_not_promise_the_budget_to_a_hidden_creator(): void
    {
        $this->actingAs($this->creatorUser(hideBudget: true))
            ->get(route('creator.dashboard'))
            ->assertSuccessful()
            ->assertDontSee('budget consommé');

        $this->actingAs($this->creatorUser(hideBudget: false))
            ->get(route('creator.dashboard'))
            ->assertSuccessful()
            ->assertSee('budget consommé');
    }

    #[Test]
    public function the_setting_is_off_by_default_and_readable_from_the_model(): void
    {
        $creator = Creator::factory()->create();

        $this->assertFalse($creator->hidesBudget());

        $creator->forceFill(['hide_budget' => true])->save();

        $this->assertTrue($creator->fresh()->hidesBudget());
    }
}
