<?php

namespace Tests\Feature\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clippers\ClipperPrizes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Où est passé l'argent d'un clippeur.
 *
 * Le solde retirable baisse dès qu'un retrait est demandé. Tant que la page ne
 * disait pas où cet argent était parti, un clippeur ne pouvait que conclure
 * qu'il en manquait — d'où la question posée à l'équipe à chaque retrait.
 *
 * Ces tests tiennent les deux choses qui rendent la page croyable : « demandé »
 * et « reçu » ne sont jamais confondus, et l'addition affichée tombe juste.
 */
class ClipperBalanceBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
        ]);
    }

    /** Crédite exactement $cents au clippeur, via le moteur de budget. */
    protected function earn(User $clipper, int $cents): void
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => max($cents, 1) * 10,
            ]);

        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $cents * 10,
        ]);

        // 1 € pour 1000 vues : $cents * 10 vues valent exactement $cents.
        app(CampaignBudgetService::class)->creditViews($clip, $cents * 10, "clip:{$clip->id}:snapshot:1");
    }

    protected function payout(User $clipper, int $cents, PayoutStatus $status): Payout
    {
        return Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'amount_cents' => $cents,
            'status' => $status,
            'requested_at' => now(),
        ]);
    }

    #[Test]
    public function a_requested_payout_is_awaiting_transfer_not_already_paid(): void
    {
        $clipper = $this->clipper();
        $this->earn($clipper, 10_000);
        $this->payout($clipper, 3_000, PayoutStatus::Requested);

        $clipper->refresh();

        $this->assertSame(3_000, $clipper->pendingPayoutCents(), 'Un retrait demandé est en route.');
        $this->assertSame(0, $clipper->paidPayoutCents(), 'Il n\'a rien reçu.');
        $this->assertSame(7_000, $clipper->availableBalanceCents());
    }

    #[Test]
    public function a_paid_payout_moves_from_awaiting_to_received(): void
    {
        $clipper = $this->clipper();
        $this->earn($clipper, 10_000);
        $payout = $this->payout($clipper, 3_000, PayoutStatus::Requested);

        $payout->forceFill(['status' => PayoutStatus::Paid])->save();
        $clipper->refresh();

        $this->assertSame(0, $clipper->pendingPayoutCents());
        $this->assertSame(3_000, $clipper->paidPayoutCents());

        // Le solde ne bouge pas : l'argent avait déjà quitté le retirable à la
        // demande. Seule l'étiquette change, et c'est bien ce qu'on veut dire
        // au clippeur.
        $this->assertSame(7_000, $clipper->availableBalanceCents());
    }

    #[Test]
    public function the_two_halves_rebuild_the_immobilised_amount(): void
    {
        $clipper = $this->clipper();
        $this->earn($clipper, 50_000);

        $this->payout($clipper, 1_000, PayoutStatus::Requested);
        $this->payout($clipper, 2_000, PayoutStatus::Processing);
        $this->payout($clipper, 4_000, PayoutStatus::Paid);

        // Annulé : l'argent est revenu au solde, il n'est dans aucune des deux.
        $this->payout($clipper, 9_000, PayoutStatus::Cancelled);

        $clipper->refresh();

        $this->assertSame(3_000, $clipper->pendingPayoutCents());
        $this->assertSame(4_000, $clipper->paidPayoutCents());
        $this->assertSame(
            $clipper->lockedPayoutCents(),
            $clipper->pendingPayoutCents() + $clipper->paidPayoutCents(),
            'Tout ce qui est immobilisé est soit en route, soit déjà arrivé.',
        );
    }

    #[Test]
    public function the_dashboard_shows_only_the_total_earned(): void
    {
        $clipper = $this->clipper();
        $this->earn($clipper, 10_000);
        $this->payout($clipper, 3_000, PayoutStatus::Requested);

        // Un seul chiffre sur le tableau de bord : tout ce qui a été gagné. Le détail
        // (retirable, en attente de virement, déjà versé) est sur la page Revenus.
        $this->actingAs($clipper)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Total gagné')
            ->assertSee('100,00 €')
            ->assertDontSee('Solde retirable')
            ->assertDontSee('En attente de virement')
            ->assertDontSee('Déjà versé');
    }

    #[Test]
    public function the_dashboard_addition_adds_up(): void
    {
        $clipper = $this->clipper();
        $this->earn($clipper, 10_000);
        $this->payout($clipper, 2_000, PayoutStatus::Requested);
        $this->payout($clipper, 5_000, PayoutStatus::Paid);

        $clipper->refresh();

        $prizes = app(ClipperPrizes::class)->for($clipper);

        $this->assertSame(
            $prizes['total_cents'],
            $clipper->availableBalanceCents()
                + $clipper->pendingPayoutCents()
                + $clipper->paidPayoutCents()
                + $prizes['pending_cents'],
            'Le total généré doit se retrouver poste par poste, sinon la page ment.',
        );

        // Le tableau de bord ne détaille plus l'addition : il ne montre que le total gagné.
        $this->actingAs($clipper)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('100,00 €')
            ->assertDontSee('100,00 € générés');
    }

    #[Test]
    public function the_earnings_page_separates_money_in_transit_from_money_received(): void
    {
        $clipper = $this->clipper();
        $this->earn($clipper, 10_000);
        $this->payout($clipper, 2_000, PayoutStatus::Requested);
        $this->payout($clipper, 5_000, PayoutStatus::Paid);

        $this->actingAs($clipper)
            ->get(route('earnings.index'))
            ->assertSuccessful()
            ->assertSee('En attente de virement')
            ->assertSee('Demandé, pas encore reçu')
            ->assertSee('déjà versés')
            ->assertDontSee('Demandé ou versé');
    }

    #[Test]
    public function the_dashboard_no_longer_lists_payout_figures(): void
    {
        $clipper = $this->clipper();
        $this->earn($clipper, 10_000);

        $this->actingAs($clipper)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Total gagné')
            ->assertDontSee('aucun retrait en cours');
    }
}
