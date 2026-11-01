<?php

namespace Tests\Feature\Clipper;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Widgets\ActionRequired;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\PrizeClaim;
use App\Models\User;
use App\Services\Clippers\ClipperPrizes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un classement est fini, le clippeur est dans les prix, et son solde est encore à zéro parce que
 * l'équipe n'a pas attribué les récompenses. Il doit le voir, et pouvoir le réclamer.
 */
class PrizeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(string $pseudo): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => $pseudo, 'name' => 'Nom '.$pseudo,
            'country' => 'FR', 'paypal_email' => $pseudo.'@paypal.test', 'profile_completed_at' => now(),
        ]);
    }

    /** Un classement avec 50 € / 30 € aux vues et 20 € aux clips. */
    protected function contest(array $attributes = []): Campaign
    {
        return Campaign::factory()->contest([5_000, 3_000], [2_000])->create(array_merge([
            'title' => 'Momo Rocma',
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'contest_post_min_views' => 100,
        ], $attributes));
    }

    protected function clip(Campaign $campaign, User $user, int $views, array $extra = []): Clip
    {
        return Clip::factory()->create(array_merge([
            'campaign_id' => $campaign->id, 'user_id' => $user->id, 'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved, 'views_total' => $views, 'earned_cents' => 0,
            'posted_at' => now()->subDays(5), 'submitted_at' => now()->subDays(4),
        ], $extra));
    }

    // ------------------------------------------------------------------

    #[Test]
    public function a_finished_contest_shows_the_prize_as_acquired_but_not_yet_paid(): void
    {
        $campaign = $this->contest();
        $winner = $this->clipper('winner');
        $this->clip($campaign, $winner, 9_000);
        $this->clip($campaign, $this->clipper('second'), 4_000);

        $prizes = app(ClipperPrizes::class)->for($winner);

        $this->assertSame(0, $prizes['credited_cents']);          // le solde est bien à zéro…
        $this->assertSame(5_000 + 2_000, $prizes['pending_cents']); // …mais 50 € (vues) + 20 € (clips) sont acquis
        $this->assertSame(7_000, $prizes['total_cents']);
        $this->assertCount(2, $prizes['rows']);
        $this->assertSame('pending', $prizes['rows'][0]['state']);
        $this->assertSame([['campaign_id' => $campaign->id, 'title' => 'Momo Rocma', 'cents' => 7_000, 'claimed' => false]], $prizes['claimable']);
    }

    #[Test]
    public function a_running_contest_only_shows_a_provisional_figure_outside_the_total(): void
    {
        $campaign = $this->contest(['ends_at' => now()->addDays(2)]);
        $leader = $this->clipper('leader');
        $this->clip($campaign, $leader, 9_000);

        $prizes = app(ClipperPrizes::class)->for($leader);

        $this->assertSame('live', $prizes['rows'][0]['state']);
        $this->assertSame(7_000, $prizes['live_cents']);
        $this->assertSame(0, $prizes['pending_cents']);
        $this->assertSame(0, $prizes['total_cents']);   // un classement qui court n'est pas encore acquis
        $this->assertSame([], $prizes['claimable']);
    }

    #[Test]
    public function an_awarded_prize_is_not_counted_twice(): void
    {
        $campaign = $this->contest(['prizes_paid_at' => now()]);
        $winner = $this->clipper('winner');
        // Le prix attribué a été crédité sur le clip : il est déjà dans les gains validés.
        $this->clip($campaign, $winner, 9_000, ['earned_cents' => 7_000, 'bonus_cents' => 7_000]);

        $prizes = app(ClipperPrizes::class)->for($winner);

        $this->assertSame(7_000, $prizes['credited_cents']);
        $this->assertSame(0, $prizes['pending_cents']);
        $this->assertSame(7_000, $prizes['total_cents']);
        $this->assertSame('awarded', $prizes['rows'][0]['state']);
    }

    #[Test]
    public function the_dashboard_shows_the_prizes_and_the_claim_button(): void
    {
        $campaign = $this->contest();
        $winner = $this->clipper('winner');
        $this->clip($campaign, $winner, 9_000);

        $this->actingAs($winner)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Mes gains')
            ->assertSee('Total généré')
            ->assertSee('Prix à recevoir')
            ->assertSee('70,00')
            ->assertSee('À verser')
            ->assertSee('Réclamer mes prix · Momo Rocma');
    }

    #[Test]
    public function claiming_records_one_request_and_pays_nothing(): void
    {
        $campaign = $this->contest();
        $winner = $this->clipper('winner');
        $this->clip($campaign, $winner, 9_000);

        $this->actingAs($winner)->post(route('prizes.claim', $campaign))->assertRedirect()->assertSessionHas('status');
        $this->actingAs($winner)->post(route('prizes.claim', $campaign))->assertRedirect();

        $this->assertSame(1, PrizeClaim::count());
        $this->assertSame(0, $winner->fresh()->availableBalanceCents());
        $this->assertNull($campaign->fresh()->prizes_paid_at);

        $this->actingAs($winner)->get(route('dashboard'))->assertSee('Demande envoyée');
    }

    #[Test]
    public function nobody_can_claim_without_an_acquired_prize(): void
    {
        $campaign = $this->contest();
        $winner = $this->clipper('winner');
        $this->clip($campaign, $winner, 9_000);
        $outsider = $this->clipper('outsider');                       // pas de clip dans la campagne
        $running = $this->contest(['ends_at' => now()->addDay()]);    // campagne pas finie
        $this->clip($running, $winner, 9_000);

        $this->actingAs($outsider)->post(route('prizes.claim', $campaign))->assertForbidden();
        $this->actingAs($winner)->post(route('prizes.claim', $running))->assertForbidden();
        $this->assertSame(0, PrizeClaim::count());
    }

    #[Test]
    public function a_clipper_without_any_prize_sees_no_prize_card(): void
    {
        $this->actingAs($this->clipper('nobody'))
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertDontSee('Prix à recevoir');
    }

    #[Test]
    public function the_team_is_reminded_of_finished_contests_and_claims(): void
    {
        $campaign = $this->contest();
        $winner = $this->clipper('winner');
        $this->clip($campaign, $winner, 9_000);
        PrizeClaim::create(['user_id' => $winner->id, 'campaign_id' => $campaign->id, 'claimed_at' => now()]);

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']));

        $html = Livewire::test(ActionRequired::class)
            ->assertSee('Classements à clôturer')
            ->assertSee('Prix réclamés')
            ->html();

        $this->assertStringContainsString('is-danger', $html);

        // Une fois les prix attribués, la campagne ne réclame plus rien.
        $campaign->forceFill(['prizes_paid_at' => now()])->save();

        Livewire::test(ActionRequired::class)->assertSee("Rien n'attend l'équipe", false);
    }
}
