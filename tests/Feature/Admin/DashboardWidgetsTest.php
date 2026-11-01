<?php

namespace Tests\Feature\Admin;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\UserRole;
use App\Filament\Pages\CampaignStatsPage;
use App\Filament\Widgets\ActionRequired;
use App\Filament\Widgets\LiveCampaigns;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Le tableau de bord : ce qui attend l'équipe, et les campagnes en cours. */
class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']));
    }

    #[Test]
    public function the_action_block_says_when_nothing_is_waiting(): void
    {
        Livewire::test(ActionRequired::class)
            ->assertSee("Rien n'attend l'équipe", false)
            ->assertSee('Retraits à valider')
            ->assertSee('Messages sans réponse');
    }

    #[Test]
    public function the_action_block_counts_what_needs_a_decision_and_links_to_it(): void
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::Active]);
        $rejected = Clip::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $clipper->id, 'status' => ClipStatus::Rejected]);
        ModerationLog::record(ModerationAction::ClipOutsideWindow, $rejected, null, 'Trop tôt');

        $component = Livewire::test(ActionRequired::class)
            ->assertDontSee("Rien n'attend", false)
            ->assertSee('Clippeurs signalés')
            ->assertSee('Clips refusés à revoir');

        $html = $component->html();
        $this->assertStringContainsString('is-danger', $html);   // les signalés
        $this->assertStringContainsString('activeTab=rejected', $html);
    }

    #[Test]
    public function the_live_campaigns_block_shows_running_campaigns_and_flags_an_overdue_one(): void
    {
        Campaign::factory()->create(['title' => 'Campagne en cours', 'status' => CampaignStatus::Active, 'ends_at' => now()->addDays(3)]);
        Campaign::factory()->create(['title' => 'Campagne oubliée', 'status' => CampaignStatus::Active, 'ends_at' => now()->subHours(5)]);
        Campaign::factory()->create(['title' => 'Campagne en brouillon', 'status' => CampaignStatus::Draft]);

        Livewire::test(LiveCampaigns::class)
            ->assertSee('Campagne en cours')
            ->assertSee('Se termine')
            ->assertSee('Campagne oubliée')
            ->assertSee('Date de fin dépassée')
            ->assertDontSee('Campagne en brouillon');
    }

    #[Test]
    public function the_live_campaigns_block_shows_the_leaders_of_a_running_contest(): void
    {
        $campaign = Campaign::factory()->contest([5_000])->create(['title' => 'Classement du mois', 'status' => CampaignStatus::Active, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(2)]);
        $leader = User::factory()->create(['role' => UserRole::Clipper, 'name' => 'Yassin El Kallati']);
        Clip::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $leader->id, 'status' => ClipStatus::Approved, 'views_total' => 12_345, 'posted_at' => now()->subHours(3), 'submitted_at' => now()->subHours(2)]);

        Livewire::test(LiveCampaigns::class)
            ->assertSee('Classement du mois')
            ->assertSee('Yassin El Kallati')
            ->assertSee('12 345');
    }

    #[Test]
    public function the_stats_page_opens_on_the_campaign_named_in_the_address(): void
    {
        Campaign::factory()->create(['title' => 'Plus récente']);
        $target = Campaign::factory()->create(['title' => 'Celle demandée', 'starts_at' => now()->subYear()]);

        Livewire::withQueryParams(['campaignId' => $target->id])
            ->test(CampaignStatsPage::class)
            ->assertSet('campaignId', $target->id);
    }
}
