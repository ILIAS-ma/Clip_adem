<?php

namespace Tests\Feature\Reporting;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Clip;
use App\Models\User;
use App\Services\Reporting\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Classement depuis toujours, par catégorie de campagnes.
 */
class CategoryLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected ReportingService $reporting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reporting = app(ReportingService::class);
    }

    protected function clipper(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => fake()->unique()->userName(),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
            ...$attributes,
        ]);
    }

    protected function campaignIn(?Category $category): Campaign
    {
        return Campaign::factory()->create([
            'category_id' => $category?->getKey(),
            'status' => CampaignStatus::Active,
        ]);
    }

    protected function clip(Campaign $campaign, User $clipper, int $views, int $earnedCents, ClipStatus $status = ClipStatus::Approved): Clip
    {
        return Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'status' => $status,
            'views_total' => $views,
            'earned_cents' => $earnedCents,
        ]);
    }

    #[Test]
    public function it_sums_views_and_earnings_across_every_campaign_of_the_category(): void
    {
        $gaming = Category::factory()->create();
        $music = Category::factory()->create();

        $maya = $this->clipper();
        $leo = $this->clipper();

        $this->clip($this->campaignIn($gaming), $maya, 10_000, 1_000);
        $this->clip($this->campaignIn($gaming), $maya, 5_000, 500);
        $this->clip($this->campaignIn($gaming), $leo, 40_000, 4_000);
        // Hors catégorie : ne doit pas compter.
        $this->clip($this->campaignIn($music), $maya, 99_000, 9_900);
        $this->clip($this->campaignIn(null), $maya, 99_000, 9_900);

        $board = $this->reporting->topClippersInCategory($gaming);

        $this->assertSame([$leo->id, $maya->id], $board->pluck('id')->all());
        $this->assertSame(15_000, $board[1]->views);
        $this->assertSame(1_500, $board[1]->earned_cents);

        $this->assertSame(['views' => 55_000, 'earned_cents' => 5_500], $this->reporting->categoryTotals($gaming));
    }

    #[Test]
    public function it_ignores_unvalidated_views_and_banned_clippers(): void
    {
        $category = Category::factory()->create();
        $campaign = $this->campaignIn($category);

        $maya = $this->clipper();
        $banned = $this->clipper(['is_banned' => true]);

        $this->clip($campaign, $maya, 10_000, 1_000);
        $this->clip($campaign, $maya, 80_000, 0, ClipStatus::PendingReview);
        $this->clip($campaign, $banned, 90_000, 9_000);

        $board = $this->reporting->topClippersInCategory($category);

        $this->assertSame([$maya->id], $board->pluck('id')->all());
        $this->assertSame(10_000, $board[0]->views);
    }

    #[Test]
    public function soft_deleted_campaigns_still_count_toward_the_lifetime_ranking(): void
    {
        $category = Category::factory()->create();
        $campaign = $this->campaignIn($category);
        $maya = $this->clipper();

        $this->clip($campaign, $maya, 10_000, 1_000);
        $campaign->delete();

        $this->assertSame(1_000, $this->reporting->topClippersInCategory($category)->first()?->earned_cents);
    }

    #[Test]
    public function the_leaderboard_page_shows_the_requested_category(): void
    {
        $gaming = Category::factory()->create(['name' => 'Gaming', 'slug' => 'gaming']);
        $music = Category::factory()->create(['name' => 'Musique', 'slug' => 'musique']);

        $viewer = $this->clipper();
        $gamer = $this->clipper(['name' => 'Gamer Pro']);
        $singer = $this->clipper(['name' => 'Voix d’Or']);

        $this->clip($this->campaignIn($gaming), $gamer, 3_000, 300);
        $this->clip($this->campaignIn($music), $singer, 12_000, 1_234);

        // Sans paramètre : la catégorie qui a le plus rapporté, même si elle
        // vient après dans l'ordre alphabétique.
        $html = $this->actingAs($viewer)->get(route('leaderboard.index'))->getContent();
        $section = $this->categorySection($this->actingAs($viewer)->get(route('leaderboard.index')));

        $this->assertStringContainsString('Voix d’Or', $section);
        $this->assertStringContainsString('12,34', $section);
        $this->assertStringNotContainsString('Gamer Pro', $section);
        $this->assertLessThan(strpos($html, '?categorie=gaming'), strpos($html, '?categorie=musique'), 'Musique, qui rapporte plus, doit être le premier onglet.');

        $section = $this->categorySection(
            $this->actingAs($viewer)->get(route('leaderboard.index', ['categorie' => 'gaming'])),
        );

        $this->assertStringContainsString('Gamer Pro', $section);
        $this->assertStringNotContainsString('Voix d’Or', $section);
    }

    /**
     * Le bloc « Par catégorie » seul : les mêmes noms figurent aussi dans le
     * classement général, au-dessus.
     */
    protected function categorySection($response): string
    {
        $html = $response->assertSuccessful()->getContent();
        $start = strpos($html, 'id="par-categorie"');

        $this->assertNotFalse($start, 'Le bloc « Par catégorie » doit être affiché.');

        return substr($html, $start, strpos($html, '</main>', $start) - $start);
    }

    #[Test]
    public function the_section_is_hidden_until_a_category_exists(): void
    {
        $this->actingAs($this->clipper())
            ->get(route('leaderboard.index'))
            ->assertSuccessful()
            ->assertDontSee('Par catégorie');
    }

    #[Test]
    public function deleting_a_category_keeps_its_campaigns(): void
    {
        $category = Category::factory()->create();
        $campaign = $this->campaignIn($category);

        $category->delete();

        $this->assertNull($campaign->fresh()->category_id);
    }
}
