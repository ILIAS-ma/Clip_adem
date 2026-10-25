<?php

namespace Tests\Feature\Admin;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Campaigns\RelationManagers\FundingsRelationManager;
use App\Filament\Widgets\CampaignBudgetOverview;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sur la fiche d'un classement, l'admin ne voit ni « consommé » ni « restant »,
 * ni onglet d'encaissements : ses prix sont annoncés, pas consommés aux vues.
 * Une campagne payée aux vues, elle, garde tout cela.
 */
class ContestAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]));
    }

    #[Test]
    public function the_header_of_a_contest_has_no_consumed_remaining_or_cpm(): void
    {
        $contest = Campaign::factory()->contest([15_000, 10_000])->create(['status' => CampaignStatus::Active]);

        Livewire::test(CampaignBudgetOverview::class, ['record' => $contest])
            ->assertSee('Budget total')
            ->assertSee('Vues générées')
            ->assertDontSee('Consommé')
            ->assertDontSee('Restant')
            ->assertDontSee('CPM réel')
            ->assertDontSee('Disponible pour les clippeurs');
    }

    #[Test]
    public function the_header_of_a_campaign_paid_per_view_keeps_its_budget_figures(): void
    {
        $paid = Campaign::factory()->withRate(Platform::TikTok)->create(['status' => CampaignStatus::Active]);

        Livewire::test(CampaignBudgetOverview::class, ['record' => $paid])
            ->assertSee('Consommé')
            ->assertSee('Restant')
            ->assertSee('CPM réel');
    }

    #[Test]
    public function a_contest_has_no_receipts_tab_but_a_paid_campaign_does(): void
    {
        $contest = Campaign::factory()->contest()->create();
        $paid = Campaign::factory()->withRate(Platform::TikTok)->create();

        $this->assertFalse(FundingsRelationManager::canViewForRecord($contest, EditCampaign::class));
        $this->assertTrue(FundingsRelationManager::canViewForRecord($paid, EditCampaign::class));
    }

    #[Test]
    public function a_moderator_never_sees_the_receipts_tab(): void
    {
        $paid = Campaign::factory()->withRate(Platform::TikTok)->create();

        $this->actingAs(User::factory()->create(['role' => UserRole::Moderator]));

        $this->assertFalse(FundingsRelationManager::canViewForRecord($paid, EditCampaign::class));
    }
}
