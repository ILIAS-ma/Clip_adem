<?php

namespace Tests\Feature\Budget;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\User;
use App\Support\Budget\CreditOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Volume minimum de clips avant rémunération.
 *
 * Une campagne peut exiger cinq clips plutôt qu'un seul coup de chance. En
 * dessous, les vues sont comptées mais rien n'est crédité.
 *
 * Ce qui doit tenir, et c'est tout l'enjeu : rien ne doit être perdu. Le
 * crédit se calcule sur le total de vues moins les vues déjà payées, donc le
 * relevé qui suit la validation du clip manquant doit rattraper l'arriéré de
 * tous les précédents. Si ce n'était pas le cas, la règle volerait les
 * clippeurs au lieu de les discipliner.
 */
class MinimumClipsTest extends TestCase
{
    use RefreshDatabase;

    protected CampaignBudgetService $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->budget = app(CampaignBudgetService::class);
    }

    protected function campagne(?int $minimum, int $budget = 100_000): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'creator_id' => Creator::factory()->create()->getKey(),
                'status' => CampaignStatus::Active,
                'budget_total_cents' => $budget,
                'min_clips_per_clipper' => $minimum,
            ]);
    }

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya.clips'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ]);
    }

    protected function clip(Campaign $campagne, User $user, ClipStatus $statut = ClipStatus::Approved): Clip
    {
        return Clip::factory()->create([
            'campaign_id' => $campagne->getKey(),
            'user_id' => $user->getKey(),
            'platform' => Platform::TikTok,
            'status' => $statut,
        ]);
    }

    #[Test]
    public function below_the_minimum_nothing_is_credited(): void
    {
        $campagne = $this->campagne(minimum: 3);
        $user = $this->clipper();

        // Deux clips validés sur les trois exigés.
        $premier = $this->clip($campagne, $user);
        $this->clip($campagne, $user);

        $resultat = $this->budget->creditViews($premier, 50_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::TooFewClips, $resultat->outcome);
        $this->assertSame(0, $resultat->creditedCents);
        $this->assertSame(0, $campagne->fresh()->spent_cents);

        // Et surtout : aucune vue n'est marquée payée, sinon l'arriéré
        // deviendrait irrécupérable.
        $this->assertSame(0, $premier->fresh()->paid_views);
    }

    #[Test]
    public function reaching_the_minimum_recovers_the_whole_backlog(): void
    {
        /*
         * Le test qui compte. Entre-temps le clip a fait 50 000 vues sans
         * rien rapporter ; au relevé suivant, le crédit porte sur le total
         * moins les vues déjà payées — donc sur les 50 000 d'un coup, pas
         * seulement sur les nouvelles.
         *
         * Sans cette propriété, la règle ferait perdre au clippeur tout ce
         * qu'il a produit avant d'atteindre le compte.
         */
        $campagne = $this->campagne(minimum: 3);
        $user = $this->clipper();

        $premier = $this->clip($campagne, $user);
        $this->clip($campagne, $user);

        $this->budget->creditViews($premier, 50_000, 'clip:1:snapshot:1');

        $this->assertSame(0, $premier->fresh()->paid_views);

        // Le troisième clip est validé : le compte est atteint.
        $this->clip($campagne, $user);

        $resultat = $this->budget->creditViews($premier->fresh(), 50_000, 'clip:1:snapshot:2');

        $this->assertSame(CreditOutcome::Credited, $resultat->outcome);
        // 50 000 vues à 1 € les 1000 = 50 €.
        $this->assertSame(5_000, $resultat->creditedCents);
        $this->assertSame(50_000, $premier->fresh()->paid_views);
    }

    #[Test]
    public function only_approved_clips_count(): void
    {
        /*
         * Sinon cinq soumissions bâclées suffiraient à ouvrir le robinet, ce
         * que la règle existe précisément pour empêcher.
         */
        $campagne = $this->campagne(minimum: 3);
        $user = $this->clipper();

        $premier = $this->clip($campagne, $user);
        $this->clip($campagne, $user, ClipStatus::PendingReview);
        $this->clip($campagne, $user, ClipStatus::PendingReview);

        $resultat = $this->budget->creditViews($premier, 50_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::TooFewClips, $resultat->outcome);
        $this->assertSame(1, $campagne->payableClipCountFor($user->getKey()));
    }

    #[Test]
    public function the_clips_of_another_clipper_do_not_count(): void
    {
        // Le minimum est par clippeur : s'appuyer sur le volume des autres
        // viderait la règle de son sens.
        $campagne = $this->campagne(minimum: 3);
        $maya = $this->clipper();
        $rival = $this->clipper();

        $sien = $this->clip($campagne, $maya);
        $this->clip($campagne, $rival);
        $this->clip($campagne, $rival);
        $this->clip($campagne, $rival);

        $resultat = $this->budget->creditViews($sien, 50_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::TooFewClips, $resultat->outcome);
    }

    #[Test]
    public function without_a_minimum_the_first_clip_is_paid(): void
    {
        // La colonne est nullable : l'absence d'exigence doit se distinguer
        // d'une exigence de zéro, qui ne veut rien dire.
        $campagne = $this->campagne(minimum: null);
        $user = $this->clipper();

        $clip = $this->clip($campagne, $user);

        $resultat = $this->budget->creditViews($clip, 50_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::Credited, $resultat->outcome);
        $this->assertSame(5_000, $resultat->creditedCents);
    }

    #[Test]
    public function the_campaign_page_announces_the_rule_and_the_progress(): void
    {
        /*
         * La ligne du tableau ne suffit pas : quelqu'un qui voit ses vues
         * monter sans que ses gains bougent cherche une panne, pas une règle.
         */
        $campagne = $this->campagne(minimum: 5);
        $user = $this->clipper();

        $this->clip($campagne, $user);
        $this->clip($campagne, $user);

        $this->actingAs($user)
            ->get(route('campaigns.show', $campagne))
            ->assertSuccessful()
            ->assertSee('5 clips soumis avant d\'être rémunéré', escape: false)
            ->assertSee('vous en avez 2')
            ->assertSee('2 / 5');
    }

    #[Test]
    public function the_page_stops_warning_once_the_count_is_reached(): void
    {
        $campagne = $this->campagne(minimum: 2);
        $user = $this->clipper();

        $this->clip($campagne, $user);
        $this->clip($campagne, $user);

        $this->actingAs($user)
            ->get(route('campaigns.show', $campagne))
            ->assertSuccessful()
            ->assertDontSee('avant d\'être rémunéré', escape: false)
            ->assertSee('2 / 2');
    }
}
