<?php

namespace Tests\Feature\Admin;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Pages\ClipReviewPage;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** La vérification finale : l'équipe ouvre un clippeur, regarde ses clips en galerie, valide ou refuse. */
class ClipReviewTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected User $admin;

    protected User $yass;

    protected User $rkn;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('clipping.posting_window.enforced_from', now()->addYear()->toDateTimeString());

        $this->campaign = Campaign::factory()->contest([8_000], [])->create([
            'title' => 'DJIBRIL94 x LIGUE1', 'status' => CampaignStatus::Active,
            'starts_at' => now()->subDays(3), 'ends_at' => now()->addDay(),
        ]);

        $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $this->yass = $this->clipper('Yss');
        $this->rkn = $this->clipper('Rkn');
    }

    protected function clipper(string $pseudo): User
    {
        $user = User::factory()->create(['role' => UserRole::Clipper, 'pseudo' => $pseudo, 'email' => strtolower($pseudo).'@example.com']);
        SocialAccount::factory()->create(['user_id' => $user->id, 'platform' => Platform::TikTok, 'handle' => strtolower($pseudo)]);

        return $user;
    }

    protected function clip(User $user, int $views): Clip
    {
        return Clip::factory()->create([
            'campaign_id' => $this->campaign->id, 'user_id' => $user->id, 'platform' => Platform::TikTok,
            'social_account_id' => SocialAccount::where('user_id', $user->id)->value('id'),
            'status' => ClipStatus::Approved, 'views_total' => $views, 'earned_cents' => 0,
            'posted_at' => now()->subDay(), 'submitted_at' => now()->subHours(5),
            'url' => 'https://www.tiktok.com/@x/video/'.fake()->unique()->numerify('##########'),
        ]);
    }

    protected function page()
    {
        return Livewire::actingAs($this->admin)->test(ClipReviewPage::class, ['campaignId' => $this->campaign->id]);
    }

    #[Test]
    public function the_page_lists_the_clippers_by_views_and_shows_the_gallery_of_one(): void
    {
        $this->clip($this->yass, 50_000);
        $this->clip($this->yass, 20_000);
        $first = $this->clip($this->rkn, 900);

        $this->page()
            ->assertSee('Yss')
            ->assertSee('Rkn')
            ->assertSee('3 clips à vérifier')
            ->call('open', $this->rkn->id)
            ->assertSee('900 vues')
            ->assertSee($first->url);

        $names = $this->page()->instance()->clippers()->pluck('name')->all();
        $this->assertSame(['Yss', 'Rkn'], $names);
    }

    #[Test]
    public function an_admin_verifies_a_clip_then_all_the_others_of_the_clipper(): void
    {
        $one = $this->clip($this->yass, 50_000);
        $two = $this->clip($this->yass, 20_000);

        $this->page()
            ->call('open', $this->yass->id)
            ->call('verify', $one->id);

        $this->assertNotNull($one->fresh()->reviewed_at);
        $this->assertSame($this->admin->id, (int) $one->fresh()->reviewed_by);
        $this->assertNull($two->fresh()->reviewed_at);

        $this->page()->call('open', $this->yass->id)->call('verifyAll');

        $this->assertNotNull($two->fresh()->reviewed_at);
        // Vérifier ne touche ni au statut ni aux vues.
        $this->assertSame(ClipStatus::Approved, $two->fresh()->status);
        $this->assertSame(20_000, $two->fresh()->views_total);
    }

    #[Test]
    public function refusing_a_clip_takes_it_out_of_the_ranking(): void
    {
        $good = $this->clip($this->yass, 50_000);
        $bad = $this->clip($this->yass, 20_000);

        $this->page()
            ->call('open', $this->yass->id)
            ->set('reason', 'Ce clip parle d’un autre streamer.')
            ->call('reject', $bad->id);

        $this->assertSame(ClipStatus::Invalidated, $bad->fresh()->status);
        $this->assertSame('Ce clip parle d’un autre streamer.', $bad->fresh()->rejection_reason);
        $this->assertSame(ClipStatus::Approved, $good->fresh()->status);
    }

    #[Test]
    public function the_clippers_do_not_get_in(): void
    {
        $this->actingAs($this->yass)->get(ClipReviewPage::getUrl())->assertForbidden();
    }
}
