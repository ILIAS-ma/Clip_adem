<?php

namespace Tests\Feature\Clipper;

use App\Enums\ClipStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use App\Services\Referrals\ReferrerLeaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Le classement des parrains : qui amène le plus de clippeurs qui participent vraiment. */
class ReferrerLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(string $pseudo, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => $pseudo, 'name' => 'Nom Réel '.$pseudo,
            'country' => 'FR', 'paypal_email' => $pseudo.'@paypal.test', 'profile_completed_at' => now(),
        ], $extra));
    }

    /** Un filleul de $parrain ; « actif » s'il a un clip validé. */
    protected function filleul(User $parrain, bool $active, ?\DateTimeInterface $referredAt = null, array $extra = []): User
    {
        $filleul = $this->clipper('f'.fake()->unique()->numberBetween(1, 999999), $extra);
        $filleul->forceFill(['referred_by' => $parrain->id, 'referred_at' => $referredAt ?? now()])->save();

        if ($active) {
            Clip::factory()->create([
                'campaign_id' => Campaign::factory()->create()->id, 'user_id' => $filleul->id, 'status' => ClipStatus::Approved,
            ]);
        }

        return $filleul;
    }

    protected function board(User $viewer, string $period = ReferrerLeaderboard::MONTH): array
    {
        return app(ReferrerLeaderboard::class)->for($viewer, $period);
    }

    // ------------------------------------------------------------------

    #[Test]
    public function referrers_are_ranked_by_active_referrals_then_by_sign_ups(): void
    {
        [$a, $b, $c] = [$this->clipper('alpha'), $this->clipper('bravo'), $this->clipper('charlie')];

        $this->filleul($a, active: true);
        $this->filleul($a, active: true);
        $this->filleul($b, active: true);
        $this->filleul($b, active: false);   // bravo : 1 actif, 2 inscrits
        $this->filleul($c, active: true);    // charlie : 1 actif, 1 inscrit

        $rows = $this->board($this->clipper('viewer'))['rows'];

        $this->assertSame(['alpha', 'bravo', 'charlie'], $rows->pluck('name')->all());
        $this->assertSame([1, 2, 3], $rows->pluck('rank')->all());
        $this->assertSame([2, 1, 1], $rows->pluck('active')->all());
        $this->assertSame([2, 2, 1], $rows->pluck('referred')->all());
    }

    #[Test]
    public function an_account_with_no_validated_clip_does_not_count_so_fake_sign_ups_do_not_climb(): void
    {
        $parrain = $this->clipper('farmer');
        foreach (range(1, 5) as $i) {
            $this->filleul($parrain, active: false);
        }

        $board = $this->board($parrain);

        $this->assertCount(0, $board['rows']);
        $this->assertNull($board['mine']->rank);
        $this->assertSame(5, $board['mine']->referred);
        $this->assertSame(0, $board['mine']->active);

        $this->actingAs($parrain)->get(route('referrals.leaderboard'))
            ->assertSuccessful()
            ->assertSee('Pas encore classé')
            ->assertSee('un clip validé');
    }

    #[Test]
    public function banned_referrers_and_banned_referrals_are_left_out(): void
    {
        $banned = $this->clipper('banni', ['is_banned' => true]);
        $this->filleul($banned, active: true);

        $parrain = $this->clipper('honnete');
        $this->filleul($parrain, active: true);
        $this->filleul($parrain, active: true, extra: ['is_banned' => true]);   // ce filleul-là ne compte pas

        $rows = $this->board($this->clipper('viewer'))['rows'];

        $this->assertSame(['honnete'], $rows->pluck('name')->all());
        $this->assertSame(1, $rows->first()->active);
    }

    #[Test]
    public function the_month_view_forgets_last_month_while_the_total_remembers(): void
    {
        $parrain = $this->clipper('ancien');
        $this->filleul($parrain, active: true, referredAt: now()->subMonths(2));

        $this->assertCount(0, $this->board($parrain, ReferrerLeaderboard::MONTH)['rows']);
        $this->assertSame(['ancien'], $this->board($parrain, ReferrerLeaderboard::ALL)['rows']->pluck('name')->all());
    }

    #[Test]
    public function you_see_your_own_rank_even_outside_the_top(): void
    {
        $me = $this->clipper('moi');
        $this->filleul($me, active: true);

        // Vingt-deux parrains avec deux filleuls actifs : je suis derrière eux tous.
        foreach (range(1, 22) as $i) {
            $other = $this->clipper('top'.$i);
            $this->filleul($other, active: true);
            $this->filleul($other, active: true);
        }

        $board = $this->board($me);

        $this->assertCount(ReferrerLeaderboard::TOP, $board['rows']);
        $this->assertSame(23, $board['ranked']);
        $this->assertSame(23, $board['mine']->rank);
        $this->assertFalse($board['rows']->contains('user_id', $me->id));
    }

    #[Test]
    public function the_page_shows_pseudos_only_and_highlights_the_viewer(): void
    {
        $parrain = $this->clipper('mayaclips');
        $this->filleul($parrain, active: true);

        $this->actingAs($this->clipper('spectateur'))
            ->get(route('referrals.leaderboard'))
            ->assertSuccessful()
            ->assertSee('Classement des parrains')
            ->assertSee('mayaclips')
            // Jamais le nom réel d'un autre clippeur sur une page que tous voient.
            ->assertDontSee('Nom Réel mayaclips');

        $this->actingAs($parrain)
            ->get(route('referrals.leaderboard'))
            ->assertSee('(vous)')
            ->assertSee('Votre place');
    }

    #[Test]
    public function an_empty_board_invites_the_first_place_to_be_taken(): void
    {
        $this->actingAs($this->clipper('seul'))
            ->get(route('referrals.leaderboard'))
            ->assertSuccessful()
            ->assertSee('Personne n\'est encore classé', false)
            ->assertSee('Copier mon lien de parrainage');
    }

    #[Test]
    public function the_prize_the_team_announces_is_shown_and_absent_otherwise(): void
    {
        $viewer = $this->clipper('viewer');

        $this->actingAs($viewer)->get(route('referrals.leaderboard'))->assertDontSee('À gagner');

        config()->set('clipping.referrals.leaderboard_prize', '20 € au meilleur parrain du mois');

        $this->actingAs($viewer)->get(route('referrals.leaderboard'))
            ->assertSee('À gagner')
            ->assertSee('20 € au meilleur parrain du mois');
    }

    #[Test]
    public function it_needs_a_login_and_is_reachable_from_the_referral_page(): void
    {
        // Un visiteur non connecté n'a pas accès : il est renvoyé, il ne voit pas le classement.
        $this->get(route('referrals.leaderboard'))->assertRedirect();

        $this->actingAs($this->clipper('viewer'))
            ->get(route('referrals.index'))
            ->assertSuccessful()
            ->assertSee(route('referrals.leaderboard'), false);
    }
}
