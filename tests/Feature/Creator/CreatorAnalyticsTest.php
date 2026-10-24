<?php

namespace Tests\Feature\Creator;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ClipViewSnapshot;
use App\Models\Creator;
use App\Models\User;
use App\Services\Creators\CampaignAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un créateur lit l'audience de ses campagnes — vues, clips, clippeurs — même
 * quand elles sont terminées et qu'elles ne paient rien aux vues.
 */
class CreatorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Creator $creator;

    protected Campaign $contest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => UserRole::Creator, 'email_verified_at' => now()]);
        $this->creator = Creator::factory()->create(['user_id' => $this->owner->id, 'is_active' => true]);

        // Un classement terminé : aucun paiement aux vues, donc aucune écriture au grand livre.
        $this->contest = Campaign::factory()->contest([9000, 6000], [3000])->create([
            'creator_id' => $this->creator->id,
            'title' => 'Campagne Snapchat',
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subDays(12),
            'ends_at' => now()->subDays(2),
        ]);
    }

    protected function clip(User $clipper, int $views, array $attributes = []): Clip
    {
        return Clip::factory()->create(array_merge([
            'campaign_id' => $this->contest->id,
            'user_id' => $clipper->id,
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
            'earned_cents' => 0,
            'posted_at' => now()->subDays(5),
            // Avant la fin : le classement arrêté n'accepte plus de clip soumis après.
            'submitted_at' => now()->subDays(5),
        ], $attributes));
    }

    protected function clipper(string $pseudo): User
    {
        return User::factory()->create(['role' => UserRole::Clipper, 'pseudo' => $pseudo, 'name' => 'Vrai Nom '.$pseudo]);
    }

    protected function snapshot(Clip $clip, int $views, int $daysAgo): void
    {
        ClipViewSnapshot::create(['clip_id' => $clip->id, 'views' => $views, 'source' => 'api', 'captured_at' => now()->subDays($daysAgo)->setTime(12, 0)]);
    }

    #[Test]
    public function the_overview_counts_views_clips_and_clippers_from_the_clips_themselves(): void
    {
        $lea = $this->clipper('lea.clips');
        $max = $this->clipper('max.edits');
        $this->clip($lea, 100_000);
        $this->clip($lea, 20_000);
        $this->clip($max, 5_000);
        // Un clip refusé ne compte pas.
        $this->clip($max, 999_999, ['status' => ClipStatus::Rejected]);

        $overview = app(CampaignAnalytics::class)->overview([$this->contest->id]);

        $this->assertSame(125_000, $overview['views']);
        $this->assertSame(3, $overview['clips']);
        $this->assertSame(2, $overview['clippers']);
        $this->assertSame(100_000, $overview['best']);
        $this->assertSame(41_666, $overview['average']);
    }

    #[Test]
    public function the_timeline_adds_up_views_day_by_day_without_counting_a_first_reading_as_a_gain(): void
    {
        $lea = $this->clipper('lea.clips');
        $a = $this->clip($lea, 5000);
        $b = $this->clip($lea, 3000);

        // A : 1 000 vues relevées il y a 4 jours (déjà là), puis +1 500, puis +2 500.
        $this->snapshot($a, 1000, 4);
        $this->snapshot($a, 2500, 3);
        $this->snapshot($a, 5000, 1);
        // B : relevé tard, avec 3 000 vues déjà acquises : un total, pas une journée de vues.
        $this->snapshot($b, 3000, 2);

        $timeline = app(CampaignAnalytics::class)->timeline([$this->contest->id]);

        $this->assertSame(now()->subDays(4)->format('Y-m-d'), $timeline['labels'][0]);
        $this->assertSame(now()->format('Y-m-d'), end($timeline['labels']));

        // Le total ne descend jamais et finit au total réel des deux clips.
        $this->assertSame([1000, 2500, 5500, 8000, 8000], $timeline['cumulative']);

        // Les gains ignorent le premier relevé de chaque clip.
        $this->assertSame([0, 1500, 0, 2500, 0], $timeline['gains']);
        $this->assertSame(2500, $timeline['peak_gain']);
    }

    #[Test]
    public function an_empty_campaign_gives_empty_series_not_an_error(): void
    {
        $analytics = app(CampaignAnalytics::class);

        $this->assertSame([], $analytics->timeline([$this->contest->id])['labels']);
        $this->assertSame([], $analytics->postsPerDay([$this->contest->id])['labels']);
        $this->assertSame(0, $analytics->overview([$this->contest->id])['clips']);
        $this->assertSame([], $analytics->topClippers([$this->contest->id])->all());
    }

    #[Test]
    public function top_clippers_are_listed_by_pseudo_and_never_by_real_name(): void
    {
        $lea = $this->clipper('lea.clips');
        $this->clip($lea, 40_000);

        $top = app(CampaignAnalytics::class)->topClippers([$this->contest->id]);

        $this->assertSame('lea.clips', $top->first()->name);
        $this->assertNotSame('Vrai Nom lea.clips', $top->first()->name);
    }

    #[Test]
    public function the_distribution_buckets_clips_by_views(): void
    {
        $lea = $this->clipper('lea.clips');
        foreach ([10, 500, 5_000, 6_000, 250_000] as $views) {
            $this->clip($lea, $views);
        }

        $buckets = collect(app(CampaignAnalytics::class)->distribution([$this->contest->id]))->pluck('count')->all();

        $this->assertSame([1, 1, 2, 0, 1], $buckets);
    }

    #[Test]
    public function the_dashboard_shows_real_views_for_an_ended_contest_that_paid_nothing_per_view(): void
    {
        $lea = $this->clipper('lea.clips');
        $this->clip($lea, 80_000);
        $this->clip($this->clipper('max.edits'), 20_000);

        $this->actingAs($this->owner)
            ->get(route('creator.dashboard'))
            ->assertOk()
            // Plus de zéro : les vues viennent des clips, pas des paiements.
            ->assertSee('100 000 vues générées par 2 clips de 2 clippeurs', escape: false)
            ->assertSee('Évolution des vues')
            ->assertSee('Les clippeurs les plus actifs')
            ->assertSee('Répartition des clips')
            ->assertSee('Les clips les plus vus')
            ->assertSee('lea.clips')
            ->assertDontSee('Vrai Nom lea.clips')
            // La campagne terminée reste listée, étiquetée, avec ses chiffres.
            ->assertSee('Campagne Snapchat')
            ->assertSee('Terminée');
    }

    #[Test]
    public function the_dashboard_offers_the_support_in_the_menu_and_on_the_page(): void
    {
        $this->actingAs($this->owner)
            ->get(route('creator.dashboard'))
            ->assertOk()
            ->assertSee('Contacter le support')
            ->assertSee('Support')
            ->assertSee(route('messages.index'), escape: false);
    }

    #[Test]
    public function the_support_page_is_titled_support_for_a_creator(): void
    {
        $this->actingAs($this->owner)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Support');
    }

    #[Test]
    public function the_campaign_page_shows_the_final_podium_with_pseudos_and_rewards(): void
    {
        $lea = $this->clipper('lea.clips');
        $max = $this->clipper('max.edits');
        $this->clip($lea, 90_000);
        $this->clip($max, 40_000);

        $this->actingAs($this->owner)
            ->get(route('creator.campaigns.show', $this->contest))
            ->assertOk()
            ->assertSee('Cette campagne est terminée')
            ->assertSee('Le podium final')
            ->assertSee('Top vues')
            ->assertSee('lea.clips')
            ->assertSee('90,00 €')
            ->assertSee('60,00 €')
            ->assertDontSee('Vrai Nom lea.clips')
            ->assertSee('Les clips');
    }

    #[Test]
    public function a_running_campaign_has_no_podium_and_no_ended_banner(): void
    {
        $this->contest->forceFill(['ends_at' => now()->addDays(3)])->save();
        $this->clip($this->clipper('lea.clips'), 1_000);

        $this->actingAs($this->owner)
            ->get(route('creator.campaigns.show', $this->contest))
            ->assertOk()
            ->assertDontSee('Cette campagne est terminée')
            ->assertDontSee('Le podium final');
    }

    #[Test]
    public function the_chart_renders_the_curve_when_there_are_readings(): void
    {
        $lea = $this->clipper('lea.clips');
        $clip = $this->clip($lea, 9000);
        $this->snapshot($clip, 1000, 3);
        $this->snapshot($clip, 4000, 2);
        $this->snapshot($clip, 9000, 0);

        $this->actingAs($this->owner)
            ->get(route('creator.campaigns.show', $this->contest))
            ->assertOk()
            ->assertSee('<svg viewBox="0 0 600 200"', escape: false)
            ->assertSee('Meilleur jour');
    }

    #[Test]
    public function the_creator_sees_no_money_figures_on_the_dashboard(): void
    {
        $this->clip($this->clipper('lea.clips'), 50_000);
        $this->contest->forceFill(['spent_cents' => 30_000, 'budget_total_cents' => 30_000])->save();

        $this->actingAs($this->owner)
            ->get(route('creator.dashboard'))
            ->assertOk()
            ->assertDontSee('Dépensé')
            ->assertDontSee('engagés')
            ->assertDontSee('restants')
            ->assertDontSee('consommé');
    }

    #[Test]
    public function a_draft_campaign_is_hidden_from_the_creator(): void
    {
        $draft = Campaign::factory()->contest()->create([
            'creator_id' => $this->creator->id,
            'title' => 'Brouillon secret',
            'status' => CampaignStatus::Draft,
        ]);

        $this->actingAs($this->owner)
            ->get(route('creator.dashboard'))
            ->assertOk()
            ->assertDontSee('Brouillon secret')
            ->assertDontSee('Brouillon');

        $this->actingAs($this->owner)->get(route('creator.campaigns.show', $draft))->assertNotFound();
    }

    #[Test]
    public function the_menu_has_no_profile_tab_since_the_team_creates_the_campaigns(): void
    {
        $response = $this->actingAs($this->owner)->get(route('creator.dashboard'))->assertOk();

        $response->assertDontSee('Ma fiche')
            ->assertDontSee('Ma chaîne')
            ->assertSee('Mes campagnes')
            ->assertSee('Support');
    }

    #[Test]
    public function another_creators_campaign_stays_hidden(): void
    {
        $other = Campaign::factory()->contest()->create(['creator_id' => Creator::factory()->create()->id]);

        $this->actingAs($this->owner)->get(route('creator.campaigns.show', $other))->assertNotFound();
    }
}
