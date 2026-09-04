<?php

namespace Tests\Feature\Admin;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Exceptions\InvalidCampaignTransition;
use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function draft(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(array_merge([
                'status' => CampaignStatus::Draft,
                'budget_total_cents' => 10_000,
                'brief' => 'Utiliser le refrain, mentionner l\'créateur.',
            ], $attributes));
    }

    #[Test]
    public function a_complete_draft_can_be_activated(): void
    {
        $campaign = $this->draft();

        $campaign->transitionTo(CampaignStatus::Active);

        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);
    }

    #[Test]
    public function a_draft_without_brief_cannot_be_activated(): void
    {
        $campaign = $this->draft(['brief' => null]);

        $this->expectException(InvalidCampaignTransition::class);
        $this->expectExceptionMessage('brief est obligatoire');

        $campaign->transitionTo(CampaignStatus::Active);
    }

    #[Test]
    public function a_draft_without_an_active_rate_cannot_be_activated(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft,
            'brief' => 'Brief complet.',
        ]);

        $this->expectException(InvalidCampaignTransition::class);
        $this->expectExceptionMessage('au moins une plateforme');

        $campaign->transitionTo(CampaignStatus::Active);
    }

    #[Test]
    public function an_exhausted_campaign_cannot_be_reactivated_without_more_budget(): void
    {
        $campaign = $this->draft(['status' => CampaignStatus::Exhausted, 'spent_cents' => 10_000]);

        $this->expectException(InvalidCampaignTransition::class);
        $this->expectExceptionMessage('budget est déjà consommé');

        $campaign->transitionTo(CampaignStatus::Active);
    }

    #[Test]
    public function raising_the_budget_reopens_an_exhausted_campaign(): void
    {
        $campaign = $this->draft(['status' => CampaignStatus::Exhausted, 'spent_cents' => 10_000]);

        $campaign->forceFill(['budget_total_cents' => 20_000])->save();
        $campaign->transitionTo(CampaignStatus::Active);

        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->exhausted_at);
    }

    #[Test]
    public function a_draft_cannot_jump_straight_to_completed(): void
    {
        $campaign = $this->draft();

        $this->expectException(InvalidCampaignTransition::class);

        $campaign->transitionTo(CampaignStatus::Completed);
    }

    #[Test]
    public function only_the_engine_may_mark_a_campaign_exhausted(): void
    {
        // Draft → Exhausted n'existe pas dans la machine à états : seule la
        // consommation réelle du budget peut produire ce statut.
        $this->assertFalse(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Exhausted));
        $this->assertFalse(CampaignStatus::Paused->canTransitionTo(CampaignStatus::Exhausted));
        $this->assertTrue(CampaignStatus::Active->canTransitionTo(CampaignStatus::Exhausted));
    }

    #[Test]
    public function a_paused_campaign_stops_paying_but_stays_editable(): void
    {
        $campaign = $this->draft();
        $campaign->transitionTo(CampaignStatus::Active);
        $campaign->transitionTo(CampaignStatus::Paused);

        $this->assertFalse($campaign->acceptsCredits());
        $this->assertTrue($campaign->status->canTransitionTo(CampaignStatus::Active));
    }

    #[Test]
    public function a_campaign_outside_its_broadcast_window_pays_nothing(): void
    {
        $campaign = $this->draft([
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($campaign->acceptsCredits());
    }
}
