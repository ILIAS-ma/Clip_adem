<?php

namespace Tests\Feature\Admin;

use App\Contracts\CampaignBudgetService;
use App\Enums\AssetKind;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test de fumée du back-office.
 *
 * Les widgets sont des vues Blade : sans ce test, une erreur de template ne se
 * verrait qu'en ouvrant la page à la main.
 */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            // 2FA déjà configurée, sinon le panel redirige vers sa mise en place.
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
    }

    /** Un jeu de données minimal mais complet, pour que chaque widget ait matière. */
    protected function seedActivity(): void
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);

        $clipper = User::factory()->create(['role' => UserRole::Clipper]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => 120_000,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, 120_000, "clip:{$clip->id}:snapshot:1");

        Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'status' => PayoutStatus::Requested,
            'amount_cents' => 3_000,
        ]);

        Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::PendingReview,
            'views_total' => 4_000,
        ]);
    }

    #[Test]
    public function the_dashboard_and_every_resource_page_render(): void
    {
        $this->seedActivity();
        $admin = $this->admin();

        $pages = [
            '/admin' => 'tableau de bord',
            '/admin/creators' => 'créateurs',
            '/admin/campaigns' => 'campagnes',
            '/admin/clips' => 'clips',
            '/admin/clippers' => 'clippeurs',
            '/admin/payouts' => 'retraits',
        ];

        foreach ($pages as $url => $label) {
            $this->actingAs($admin)
                ->get($url)
                ->assertSuccessful("La page {$label} ({$url}) ne s'affiche pas.");
        }
    }

    #[Test]
    public function the_campaign_form_renders_in_creation_and_in_edition(): void
    {
        // Le formulaire de campagne porte le budget, les taux et la matière
        // première du brief : une erreur de schéma n'y serait visible qu'en
        // ouvrant la page à la main.
        $this->seedActivity();
        $admin = $this->admin();

        $campaign = Campaign::first();
        $campaign->assets()->create([
            'kind' => AssetKind::Audio,
            'label' => 'Son officiel',
            'external_url' => 'https://example.com/son.mp3',
            'is_required' => true,
        ]);

        $this->actingAs($admin)->get('/admin/campaigns/create')->assertSuccessful();
        $this->actingAs($admin)->get("/admin/campaigns/{$campaign->getKey()}/edit")->assertSuccessful();
    }

    #[Test]
    public function the_dashboard_widgets_survive_an_empty_database(): void
    {
        // Le premier lancement d'un projet est toujours sur une base vide :
        // une division par zéro dans un CPM y serait invisible en développement.
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertSuccessful();
    }

    #[Test]
    public function a_moderator_cannot_reach_the_payouts_page(): void
    {
        $moderator = User::factory()->create([
            'role' => UserRole::Moderator,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $payout = Payout::factory()->create(['status' => PayoutStatus::Requested]);

        // Le listing reste consultable, mais valider un virement ne l'est pas.
        $this->assertTrue($moderator->can('viewAny', Payout::class));
        $this->assertFalse($moderator->can('update', $payout));
    }

    #[Test]
    public function a_clipper_is_refused_everywhere_in_the_panel(): void
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);

        $this->actingAs($clipper)->get('/admin')->assertForbidden();
        $this->actingAs($clipper)->get('/admin/campaigns')->assertForbidden();
    }
}
