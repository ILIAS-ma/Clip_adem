<?php

namespace Tests\Feature\Payouts;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\PayoutRefused;
use App\Filament\Pages\CampaignPayments;
use App\Livewire\RequestPayout;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Payouts\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Les clippeurs ne demandent rien : l'équipe les paie à la fin d'une campagne et leur cagnotte baisse d'autant. */
class TeamPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('clipping.payouts.self_service', false);
        config()->set('clipping.posting_window.enforced_from', now()->addYear()->toDateTimeString());

        $this->campaign = Campaign::factory()->create(['status' => CampaignStatus::Completed, 'ends_at' => now()->subDay()]);
        $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
    }

    protected function clipper(int $earnedCents, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => UserRole::Clipper, 'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'email_verified_at' => now(), 'country' => 'FR', 'paypal_email' => 'p'.fake()->unique()->numberBetween(1, 99999).'@paypal.test',
            'profile_completed_at' => now(),
        ], $attributes));
        SocialAccount::factory()->create(['user_id' => $user->id, 'platform' => Platform::TikTok]);

        Clip::factory()->create([
            'campaign_id' => $this->campaign->id, 'user_id' => $user->id, 'platform' => Platform::TikTok,
            'social_account_id' => SocialAccount::where('user_id', $user->id)->value('id'),
            'status' => ClipStatus::Approved, 'views_total' => 1000, 'earned_cents' => $earnedCents,
            'url' => 'https://www.tiktok.com/@x/video/'.fake()->unique()->numerify('##########'),
        ]);

        return $user;
    }

    #[Test]
    public function the_clipper_sees_only_the_cagnotte_and_no_withdrawal_button(): void
    {
        $clipper = $this->clipper(7_500);

        $this->actingAs($clipper)->get(route('earnings.index'))
            ->assertSuccessful()
            ->assertSee('Ma cagnotte')
            ->assertSee('75,00')
            ->assertSee('Comment tu es payé')
            ->assertDontSee('Demander un retrait');

        // Même en appelant le composant directement, rien ne part.
        Livewire::actingAs($clipper)->test(RequestPayout::class)->call('request')->assertForbidden();
        $this->assertSame(0, Payout::count());
    }

    #[Test]
    public function the_team_pays_a_clipper_and_the_cagnotte_goes_down_once(): void
    {
        $clipper = $this->clipper(7_500);
        $service = app(PayoutService::class);

        $this->assertSame(7_500, $clipper->availableBalanceCents());

        $payout = $service->payByTeam($clipper, $this->campaign, $this->admin, 'VIR-1');

        $this->assertSame(PayoutStatus::Paid, $payout->status);
        $this->assertSame(7_500, $payout->amount_cents);
        $this->assertSame($this->campaign->id, $payout->campaign_id);
        $this->assertSame(0, $clipper->fresh()->availableBalanceCents());
        $this->assertSame(7_500, $clipper->fresh()->paidPayoutCents());

        // Payer deux fois la même campagne : refusé, rien ne bouge.
        $this->expectException(PayoutRefused::class);
        $service->payByTeam($clipper, $this->campaign, $this->admin);
    }

    #[Test]
    public function the_payment_page_lists_the_clippers_and_pays_them(): void
    {
        $rich = $this->clipper(9_000);
        $poor = $this->clipper(2_000);
        $noMethod = $this->clipper(4_000, ['paypal_email' => null]);

        $page = Livewire::actingAs($this->admin)->test(CampaignPayments::class, ['campaignId' => $this->campaign->id])
            ->assertSee('À payer : 3 clippeurs')
            ->assertSee('Aucun moyen de paiement');

        $page->call('pay', $rich->id);
        $this->assertSame(0, $rich->fresh()->availableBalanceCents());
        $this->assertSame(PayoutStatus::Paid, Payout::where('user_id', $rich->id)->first()->status);

        // Tout payer : celui sans moyen de paiement est laissé de côté, les autres sont payés.
        $page->call('payAll');
        $this->assertSame(0, $poor->fresh()->availableBalanceCents());
        $this->assertSame(4_000, $noMethod->fresh()->availableBalanceCents());
        $this->assertSame(2, Payout::count());
    }

    #[Test]
    public function only_the_super_admin_opens_the_payment_page(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->actingAs($moderator)->get(CampaignPayments::getUrl())->assertForbidden();
    }
}
