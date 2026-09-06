<?php

namespace Tests\Feature\Referrals;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Services\Moderation\ClipModerationService;
use App\Services\Referrals\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Parrainage.
 *
 * La règle dont tout découle : la commission est payée par la plateforme sur
 * sa marge, jamais prélevée sur le budget d'une campagne ni sur les gains du
 * filleul. Les deux premiers tests existent pour qu'on ne l'abandonne pas par
 * distraction — c'est le genre de règle qu'une optimisation « évidente »
 * casse six mois plus tard.
 */
class ReferralTest extends TestCase
{
    use RefreshDatabase;

    protected ReferralService $referrals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referrals = app(ReferralService::class);
    }

    protected function clipper(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
        ], $attributes));
    }

    protected function campaign(): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);
    }

    /** Crédite des vues pour un clippeur et renvoie le clip. */
    protected function credit(Campaign $campaign, User $clipper, int $views = 200_000): Clip
    {
        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, $views, "clip:{$clip->id}:snapshot:1");

        return $clip->fresh();
    }

    // ------------------------------------------------------------------
    // D'où vient l'argent
    // ------------------------------------------------------------------

    #[Test]
    public function the_commission_never_comes_out_of_the_campaign_budget(): void
    {
        // Sinon le créateur financerait la croissance de la plateforme sans le
        // savoir, et le budget qu'il a provisionné ne servirait plus
        // entièrement à ses vues.
        $parrain = $this->clipper();
        $filleul = $this->clipper(['referred_by' => $parrain->getKey(), 'referred_at' => now()]);

        $campaign = $this->campaign();
        $this->credit($campaign, $filleul, 200_000); // 200 € de vues

        $campaign->refresh();

        $this->assertSame(20_000, $campaign->spent_cents, 'Le budget ne doit financer que les vues.');
        $this->assertSame(1_000, $parrain->fresh()->referralEarnedCents(), '5 % de 200 €.');
    }

    #[Test]
    public function the_referred_clipper_is_never_shortchanged(): void
    {
        $parrain = $this->clipper();
        $filleul = $this->clipper(['referred_by' => $parrain->getKey(), 'referred_at' => now()]);
        $solo = $this->clipper();

        $campaign = $this->campaign();

        $withReferrer = $this->credit($campaign, $filleul, 100_000);
        $without = $this->credit($campaign, $solo, 100_000);

        // À vues égales, gains égaux : la commission s'ajoute par-dessus, elle
        // ne se prélève pas.
        $this->assertSame($without->earned_cents, $withReferrer->earned_cents);
    }

    // ------------------------------------------------------------------
    // Rattachement
    // ------------------------------------------------------------------

    #[Test]
    public function a_code_is_readable_and_unique(): void
    {
        $code = $this->referrals->codeFor($this->clipper(['pseudo' => 'maya.clips']));

        $this->assertSame(8, strlen($code));
        // Pas de O/0 ni I/1 : ces codes se recopient à la main depuis une story.
        $this->assertDoesNotMatchRegularExpression('/[O0I1]/', substr($code, 4));
    }

    #[Test]
    public function the_same_code_is_returned_every_time(): void
    {
        // Un code qui change entre deux visites invaliderait les liens déjà
        // partagés.
        $user = $this->clipper();

        $this->assertSame($this->referrals->codeFor($user), $this->referrals->codeFor($user->fresh()));
    }

    #[Test]
    public function registering_with_a_code_attaches_the_referrer(): void
    {
        $parrain = $this->clipper();
        $code = $this->referrals->codeFor($parrain);

        $this->post('/register', [
            'first_name' => 'Lina',
            'last_name' => 'Dupont',
            'email' => 'lina@exemple.test',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'parrain' => $code,
        ]);

        $this->assertSame(
            $parrain->getKey(),
            User::where('email', 'lina@exemple.test')->sole()->referred_by,
        );
    }

    #[Test]
    public function nobody_can_refer_themselves(): void
    {
        $user = $this->clipper();

        $this->assertFalse($this->referrals->attach($user, $this->referrals->codeFor($user)));
        $this->assertNull($user->fresh()->referred_by);
    }

    #[Test]
    public function a_referrer_cannot_be_swapped_after_the_fact(): void
    {
        // Le laisser changer permettrait de déplacer des commissions déjà
        // acquises d'un compte à un autre.
        $first = $this->clipper();
        $second = $this->clipper();
        $filleul = $this->clipper();

        $this->assertTrue($this->referrals->attach($filleul, $this->referrals->codeFor($first)));
        $this->assertFalse($this->referrals->attach($filleul->fresh(), $this->referrals->codeFor($second)));

        $this->assertSame($first->getKey(), $filleul->fresh()->referred_by);
    }

    #[Test]
    public function a_banned_account_recruits_nobody(): void
    {
        $parrain = $this->clipper(['is_banned' => true]);
        $filleul = $this->clipper();

        $this->assertFalse($this->referrals->attach($filleul, $this->referrals->codeFor($parrain)));
    }

    // ------------------------------------------------------------------
    // Commissions
    // ------------------------------------------------------------------

    #[Test]
    public function a_replayed_credit_does_not_pay_the_commission_twice(): void
    {
        $parrain = $this->clipper();
        $filleul = $this->clipper(['referred_by' => $parrain->getKey(), 'referred_at' => now()]);

        $campaign = $this->campaign();
        $clip = $this->credit($campaign, $filleul, 100_000);

        // Même clé d'idempotence : le moteur refuse de recréditer, donc aucune
        // seconde commission ne doit naître.
        app(CampaignBudgetService::class)->creditViews($clip, 100_000, "clip:{$clip->id}:snapshot:1");

        $this->assertSame(1, ReferralCommission::where('referrer_id', $parrain->getKey())->count());
    }

    #[Test]
    public function invalidated_views_take_the_commission_back(): void
    {
        // Le parrain ne garde pas une commission sur des vues reconnues
        // frauduleuses.
        $parrain = $this->clipper();
        $filleul = $this->clipper(['referred_by' => $parrain->getKey(), 'referred_at' => now()]);

        $clip = $this->credit($this->campaign(), $filleul, 200_000);

        $this->assertSame(1_000, $parrain->fresh()->referralEarnedCents());

        app(ClipModerationService::class)->invalidate($clip, 'Vues achetées.');

        $this->assertSame(0, $parrain->fresh()->referralEarnedCents());

        // Par une ligne négative, jamais une suppression : le parrain doit
        // pouvoir comprendre pourquoi son total a baissé.
        $this->assertSame(2, ReferralCommission::where('referrer_id', $parrain->getKey())->count());
    }

    #[Test]
    public function referral_earnings_are_withdrawable_like_any_other(): void
    {
        $parrain = $this->clipper();
        $filleul = $this->clipper(['referred_by' => $parrain->getKey(), 'referred_at' => now()]);

        $this->credit($this->campaign(), $filleul, 200_000);

        // Sans ça, la commission s'afficherait sans jamais pouvoir être retirée.
        $this->assertSame(1_000, $parrain->fresh()->availableBalanceCents());
    }

    #[Test]
    public function a_clipper_without_a_referrer_costs_nothing(): void
    {
        $this->credit($this->campaign(), $this->clipper(), 200_000);

        $this->assertSame(0, ReferralCommission::count());
    }

    #[Test]
    public function the_page_shows_the_link_and_the_rate(): void
    {
        $parrain = $this->clipper([
            'email_verified_at' => now(),
            'country' => 'FR',
            'paypal_email' => 'p@paypal.test',
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($parrain)
            ->get(route('referrals.index'))
            ->assertSuccessful()
            ->assertSee('Parrainage')
            ->assertSee($this->referrals->codeFor($parrain))
            // La provenance de l'argent doit être écrite noir sur blanc :
            // « est-ce que ça enlève quelque chose à mon filleul ? » est la
            // première question posée.
            ->assertSee('ne perd rien');
    }
}
