<?php

namespace Tests\Feature\Campaigns;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\InvalidCampaignTransition;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ClipViewSnapshot;
use App\Models\Creator;
use App\Models\User;
use App\Notifications\BonusGranted;
use App\Services\Campaigns\ContestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function contest(array $attributes = []): Campaign
    {
        return Campaign::factory()->contest()->create([
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(10),
            ...$attributes,
        ]);
    }

    protected function clipper(string $name = 'Clippeur'): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'name' => $name,
            'email_verified_at' => now(),
            'pseudo' => fake()->unique()->userName(),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ]);
    }

    protected function clip(Campaign $campaign, User $clipper, int $views, ClipStatus $status = ClipStatus::Approved, ?string $submittedAt = null): Clip
    {
        return Clip::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $clipper->id,
            'platform' => Platform::TikTok,
            'status' => $status,
            'views_total' => $views,
            'submitted_at' => $submittedAt ?? now()->subDays(5),
        ]);
    }

    #[Test]
    public function views_are_never_paid_in_a_contest(): void
    {
        $campaign = $this->contest();
        $clip = $this->clip($campaign, $this->clipper(), 500_000);

        app(CampaignBudgetService::class)->creditViews($clip, 500_000, "clip:{$clip->id}:snapshot:1");

        $this->assertSame(0, $clip->fresh()->earned_cents);
        $this->assertSame(0, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function clippers_are_ranked_on_validated_views(): void
    {
        $campaign = $this->contest();
        [$maya, $leo, $zoe] = [$this->clipper('Maya'), $this->clipper('Léo'), $this->clipper('Zoé')];
        $banned = User::factory()->create(['role' => UserRole::Clipper, 'is_banned' => true]);

        $this->clip($campaign, $maya, 30_000);
        $this->clip($campaign, $maya, 20_000);
        $this->clip($campaign, $leo, 40_000);
        $this->clip($campaign, $leo, 900_000, ClipStatus::PendingReview); // pas encore validé
        $this->clip($campaign, $zoe, 10_000);
        $this->clip($campaign, $banned, 999_000);

        $standings = app(ContestService::class)->standings($campaign);

        $this->assertSame(['Maya', 'Léo', 'Zoé'], $standings->pluck('name')->all());
        $this->assertSame(50_000, $standings[0]->views);
        $this->assertSame(2, $standings[0]->clips);
        $this->assertSame([1, 2, 3], $standings->pluck('rank')->all());
    }

    #[Test]
    public function a_tie_goes_to_whoever_posted_first(): void
    {
        $campaign = $this->contest();
        $late = $this->clipper('Tardif');
        $early = $this->clipper('Matinal');

        $this->clip($campaign, $late, 10_000, submittedAt: now()->subDays(1)->toDateTimeString());
        $this->clip($campaign, $early, 10_000, submittedAt: now()->subDays(6)->toDateTimeString());

        $this->assertSame('Matinal', app(ContestService::class)->standings($campaign)->first()->name);
    }

    #[Test]
    public function the_ranking_freezes_at_the_end_date(): void
    {
        $campaign = $this->contest(['ends_at' => now()->subDay()]);
        $maya = $this->clipper('Maya');
        $leo = $this->clipper('Léo');

        $mayaClip = $this->clip($campaign, $maya, 100_000);
        $leoClip = $this->clip($campaign, $leo, 50_000);

        // Relevés : avant la fin Léo menait ; Maya n'a explosé qu'après.
        ClipViewSnapshot::create(['clip_id' => $mayaClip->id, 'views' => 20_000, 'captured_at' => now()->subDays(2)]);
        ClipViewSnapshot::create(['clip_id' => $mayaClip->id, 'views' => 100_000, 'captured_at' => now()]);
        ClipViewSnapshot::create(['clip_id' => $leoClip->id, 'views' => 50_000, 'captured_at' => now()->subDays(2)]);

        $standings = app(ContestService::class)->standings($campaign);

        $this->assertSame(['Léo', 'Maya'], $standings->pluck('name')->all());
        $this->assertSame(20_000, $standings[1]->views);
    }

    #[Test]
    public function prizes_wait_for_the_end_of_the_contest(): void
    {
        $campaign = $this->contest();
        $this->clip($campaign, $this->clipper(), 10_000);

        $this->expectException(InvalidArgumentException::class);

        app(ContestService::class)->payPrizes($campaign);
    }

    #[Test]
    public function prizes_go_to_the_podium_once_and_keep_the_books_straight(): void
    {
        $campaign = $this->contest(['ends_at' => now()->subHour()]);
        [$first, $second] = [$this->clipper('Premier'), $this->clipper('Second')];

        $this->clip($campaign, $first, 90_000);
        $this->clip($campaign, $second, 40_000);

        $paid = app(ContestService::class)->payPrizes($campaign);

        $this->assertCount(2, $paid);
        $this->assertSame(10_000, $first->availableBalanceCents());
        $this->assertSame(5_000, $second->availableBalanceCents());
        // Seulement deux participants : le prix de la 3e place reste au budget.
        $this->assertSame(2_500, $campaign->fresh()->remainingCents());
        $this->assertNotNull($campaign->fresh()->prizes_paid_at);
        $this->assertSame(0, Artisan::call('budget:audit'));
        Notification::assertSentTo($first, BonusGranted::class, fn ($n) => str_contains($n->reason, '1re place'));

        $this->expectException(InvalidArgumentException::class);
        app(ContestService::class)->payPrizes($campaign->fresh());
    }

    #[Test]
    public function a_contest_needs_prizes_to_go_live(): void
    {
        $campaign = Campaign::factory()->contest([])->create([
            'status' => CampaignStatus::Draft,
            'budget_total_cents' => 1_000,
        ]);

        $this->expectException(InvalidCampaignTransition::class);
        $this->expectExceptionMessage('au moins un prix');

        $campaign->transitionTo(CampaignStatus::Active);
    }

    #[Test]
    public function the_clipper_sees_the_prizes_and_their_rank(): void
    {
        $campaign = $this->contest();
        $maya = $this->clipper('Maya');
        $this->clip($campaign, $maya, 12_000);

        $this->actingAs($maya)
            ->get(route('campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee('175 €')
            ->assertSee('à gagner')
            ->assertSee('Vous êtes')
            ->assertSee('1er')
            ->assertDontSee('/1000');

        $this->actingAs($maya)
            ->get(route('campaigns.index'))
            ->assertSuccessful()
            ->assertSee('Classement');
    }

    #[Test]
    public function the_admin_creates_a_contest_without_any_rate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $creator = Creator::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateCampaign::class)
            ->fillForm([
                'creator_id' => $creator->id,
                'title' => 'Concours du mois',
                'slug' => 'concours-du-mois',
                'mode' => 'contest',
                'contest_prizes' => [['amount' => 100], ['amount' => 50]],
                'rates' => [['platform' => 'tiktok', 'is_enabled' => true]],
                'brief' => 'Le meilleur clip gagne.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = Campaign::where('slug', 'concours-du-mois')->firstOrFail();

        $this->assertTrue($campaign->isContest());
        $this->assertSame([10_000, 5_000], $campaign->prizes());
        $this->assertSame(15_000, $campaign->budget_total_cents);
        $this->assertSame(0, $campaign->rates()->first()->rate_per_1k_cents);

        $this->actingAs($admin)->get(route('filament.admin.resources.campaigns.edit', $campaign))->assertSuccessful();

        Livewire::actingAs($admin)
            ->test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->mountAction('standings')
            ->assertSuccessful();
    }
}
