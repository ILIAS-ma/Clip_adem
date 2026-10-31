<?php

namespace Tests\Feature\Admin;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Pages\CampaignStatsPage;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Campaigns\CampaignStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** L'export Excel des statistiques d'une campagne : les chiffres de l'écran se retrouvent dans le tableau. */
class CampaignStatsExportTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected User $yass;

    protected User $rkn;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('clipping.posting_window.enforced_from', now()->addYear()->toDateTimeString());

        $this->campaign = Campaign::factory()->contest([8_000, 5_000], [3_000])->create([
            'title' => 'DJIBRIL94 x LIGUE1',
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDay(),
            'contest_post_min_views' => 100,
            'required_hashtags' => ['#iplant'],
        ]);

        $this->yass = $this->clipper('Yss', 'Yassin El Kallati');
        $this->rkn = $this->clipper('Rkn', 'Augustin Cha');

        $this->clip($this->yass, 50_000, ClipStatus::Approved);
        $this->clip($this->yass, 20_000, ClipStatus::Approved);
        $this->clip($this->rkn, 900, ClipStatus::Approved);
        $refused = $this->clip($this->rkn, 0, ClipStatus::Rejected, ['rejection_reason' => 'Publiée avant le début']);
        ModerationLog::record(ModerationAction::ClipOutsideWindow, $refused, null, 'Publiée avant le début');
    }

    protected function clipper(string $pseudo, string $name): User
    {
        $user = User::factory()->create(['role' => UserRole::Clipper, 'pseudo' => $pseudo, 'name' => $name, 'email' => strtolower($pseudo).'@example.com']);
        SocialAccount::factory()->create(['user_id' => $user->id, 'platform' => Platform::TikTok, 'handle' => strtolower($pseudo)]);

        return $user;
    }

    protected function clip(User $user, int $views, ClipStatus $status, array $extra = []): Clip
    {
        return Clip::factory()->create(array_merge([
            'campaign_id' => $this->campaign->id, 'user_id' => $user->id, 'platform' => Platform::TikTok,
            'social_account_id' => SocialAccount::where('user_id', $user->id)->value('id'),
            'status' => $status, 'views_total' => $views, 'earned_cents' => 0,
            'posted_at' => now()->subDay(), 'submitted_at' => now()->subHours(5), 'url' => 'https://www.tiktok.com/@x/video/'.fake()->unique()->numerify('##########'),
        ], $extra));
    }

    protected function staff(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create(['role' => $role, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
    }

    /** Lit le classeur : onglet => lignes. */
    protected function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
            }
        }

        $reader->close();

        return $sheets;
    }

    // ------------------------------------------------------------------

    #[Test]
    public function the_workbook_has_every_sheet_with_the_right_figures(): void
    {
        $path = app(CampaignStats::class)->export($this->campaign);
        $sheets = $this->read($path);
        @unlink($path);

        $this->assertSame(
            ['Résumé', 'Classement vues', 'Classement clips', 'Clippeurs', 'Clips', 'Par jour', 'Modération'],
            array_keys($sheets),
        );

        $summary = collect($sheets['Résumé'])->mapWithKeys(fn ($row) => [$row[0] => $row[1] ?? null]);
        $this->assertSame('DJIBRIL94 x LIGUE1', $summary['Statistiques de la campagne']);
        $this->assertEquals(4, $summary['Clips soumis']);
        $this->assertEquals(3, $summary['Clips validés']);
        $this->assertEquals(1, $summary['Clips refusés']);
        $this->assertEquals(70_900, $summary['Vues (clips validés)']);
        $this->assertEquals(1, $summary['Clips signalés']);
        $this->assertSame('#iplant', $summary['Hashtags obligatoires']);

        // Classement des vues : Yss premier avec les 80 € de la première place.
        $first = $sheets['Classement vues'][1];
        $this->assertSame('Yassin El Kallati', $first[1]);
        $this->assertEquals(70_000, $first[4]);
        $this->assertSame('80,00 €', $first[6]);
    }

    #[Test]
    public function the_clippers_sheet_gives_one_line_per_person_with_their_flags(): void
    {
        $path = app(CampaignStats::class)->export($this->campaign);
        $sheets = $this->read($path);
        @unlink($path);

        $rows = collect(array_slice($sheets['Clippeurs'], 1))->keyBy(0);

        $yass = $rows['Yassin El Kallati'];
        $this->assertSame('yss@example.com', $yass[2]);
        $this->assertEquals(2, $yass[3]);   // soumis
        $this->assertEquals(70_000, $yass[7]);
        $this->assertEquals(35_000, $yass[8]);   // moyenne par clip
        $this->assertEquals(50_000, $yass[9]);   // meilleur clip

        $rkn = $rows['Augustin Cha'];
        $this->assertEquals(2, $rkn[3]);
        $this->assertEquals(1, $rkn[5]);   // refusés
        $this->assertEquals(1, $rkn[13]);  // signalements
    }

    #[Test]
    public function the_clips_sheet_lists_every_clip_with_its_link_status_and_reason(): void
    {
        $path = app(CampaignStats::class)->export($this->campaign);
        $sheets = $this->read($path);
        @unlink($path);

        $this->assertCount(1 + 4, $sheets['Clips']);

        $refused = collect($sheets['Clips'])->first(fn ($row) => ($row[8] ?? null) === 'Refusé');
        $this->assertSame('Publiée avant le début', $refused[9]);
        $this->assertSame('Vidéo publiée hors de la campagne', $refused[12]);
        $this->assertStringStartsWith('https://www.tiktok.com/', $refused[5]);

        $this->assertCount(1 + 1, $sheets['Modération']);
    }

    #[Test]
    public function a_paid_per_view_campaign_exports_its_budget_instead_of_the_boards(): void
    {
        $paid = Campaign::factory()->create(['status' => CampaignStatus::Active, 'budget_total_cents' => 100_000]);

        $path = app(CampaignStats::class)->export($paid);
        $sheets = $this->read($path);
        @unlink($path);

        $this->assertArrayNotHasKey('Classement vues', $sheets);
        $labels = collect($sheets['Résumé'])->pluck(0);
        $this->assertTrue($labels->contains('Budget total (€)'));
        $this->assertTrue($labels->contains('Restant (€)'));
    }

    #[Test]
    public function the_stats_page_shows_the_figures_and_downloads_the_workbook(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CampaignStatsPage::class)
            ->set('campaignId', $this->campaign->id)
            ->assertSee('Télécharger')
            ->assertSee('Yassin El Kallati')
            ->assertSee('Augustin Cha')
            ->assertSee('70 900')
            ->call('export')
            ->assertFileDownloaded('stats-djibril94-x-ligue1-'.now()->format('Y-m-d').'.xlsx');
    }

    #[Test]
    public function the_campaign_page_has_an_export_button_too(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(EditCampaign::class, ['record' => $this->campaign->getRouteKey()])
            ->callAction('exportStats')
            ->assertFileDownloaded();
    }

    #[Test]
    public function a_clipper_cannot_open_the_stats_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Clipper]));

        $this->assertFalse(CampaignStatsPage::canAccess());
        $this->get('/admin/statistiques')->assertForbidden();
    }
}
