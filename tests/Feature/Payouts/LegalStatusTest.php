<?php

namespace Tests\Feature\Payouts;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\LegalStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\PayoutRefused;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\SiretReviewed;
use App\Rules\Siret;
use App\Services\Payouts\LegalStatusService;
use App\Services\Payouts\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalStatusTest extends TestCase
{
    use RefreshDatabase;

    protected const VALID_SIRET = '73282932000074';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('clipping.legal.enabled', true);
        config()->set('clipping.legal.unregistered_max_cents_per_year', 10_000);
        config()->set('clipping.legal.unregistered_max_payouts_per_year', 3);
        config()->set('clipping.payouts.minimum_cents', 1_000);
    }

    protected function clipperWithBalance(int $cents): User
    {
        $clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => fake()->unique()->userName(),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ]);

        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(['status' => CampaignStatus::Active, 'budget_total_cents' => $cents * 10]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, $cents * 10, "clip:{$clip->id}:snapshot:1");

        return $clipper->fresh();
    }

    #[Test]
    public function the_siret_rule_checks_length_and_luhn_key(): void
    {
        $this->assertTrue(Siret::isValid(self::VALID_SIRET));
        $this->assertTrue(Siret::isValid('732 829 320 00074'));
        $this->assertFalse(Siret::isValid('73282932000075'));
        $this->assertFalse(Siret::isValid('7328293200007'));
    }

    #[Test]
    public function an_unregistered_clipper_cannot_exceed_the_yearly_amount(): void
    {
        $clipper = $this->clipperWithBalance(50_000);
        $payouts = app(PayoutService::class);

        $payouts->request($clipper, 8_000);

        $this->expectException(PayoutRefused::class);
        $this->expectExceptionMessage('plafonnés à 100 € par an');

        $payouts->request($clipper, 3_000);
    }

    #[Test]
    public function an_unregistered_clipper_cannot_exceed_the_yearly_count(): void
    {
        $clipper = $this->clipperWithBalance(50_000);
        $payouts = app(PayoutService::class);

        foreach (range(1, 3) as $i) {
            $payouts->request($clipper, 1_000);
        }

        $this->expectException(PayoutRefused::class);
        $this->expectExceptionMessage('Limite de 3 retraits par an');

        $payouts->request($clipper, 1_000);
    }

    #[Test]
    public function cancelled_payouts_and_last_year_do_not_count(): void
    {
        $clipper = $this->clipperWithBalance(50_000);

        Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'amount_cents' => 9_000,
            'status' => PayoutStatus::Cancelled,
            'requested_at' => now(),
        ]);
        Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'amount_cents' => 9_000,
            'status' => PayoutStatus::Paid,
            'requested_at' => now()->subYear(),
        ]);

        $payout = app(PayoutService::class)->request($clipper, 10_000);

        $this->assertSame(10_000, $payout->amount_cents);
    }

    #[Test]
    public function a_pending_siret_keeps_the_cap_and_a_verified_one_lifts_it(): void
    {
        Notification::fake();

        $clipper = $this->clipperWithBalance(50_000);
        $service = app(LegalStatusService::class);
        $payouts = app(PayoutService::class);

        $service->declare($clipper, LegalStatus::Business, '732 829 320 00074');
        $this->assertTrue($clipper->fresh()->hasPendingSiret());

        try {
            $payouts->request($clipper->fresh(), 20_000);
            $this->fail('Un SIRET non vérifié ne doit pas lever le plafond.');
        } catch (PayoutRefused) {
        }

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $service->verify($clipper->fresh(), $admin);

        $payout = $payouts->request($clipper->fresh(), 20_000);

        $this->assertSame(20_000, $payout->amount_cents);
        Notification::assertSentTo($clipper, SiretReviewed::class, fn ($n) => $n->accepted);
    }

    #[Test]
    public function verifying_a_siret_does_not_cost_the_clipper_automatic_payout_approval(): void
    {
        Notification::fake();
        config()->set('clipping.payouts.auto_approve_below_cents', 5_000);

        $clipper = $this->clipperWithBalance(50_000);
        $service = app(LegalStatusService::class);
        $service->declare($clipper, LegalStatus::Business, self::VALID_SIRET);
        $service->verify($clipper->fresh());

        $payout = app(PayoutService::class)->request($clipper->fresh(), 2_000);

        $this->assertSame(PayoutStatus::Approved, $payout->status);
    }

    #[Test]
    public function changing_the_siret_resets_the_verification(): void
    {
        Notification::fake();

        $clipper = $this->clipperWithBalance(5_000);
        $service = app(LegalStatusService::class);

        $service->declare($clipper, LegalStatus::Business, self::VALID_SIRET);
        $service->verify($clipper->fresh());
        $this->assertTrue($clipper->fresh()->isVerifiedBusiness());

        $service->declare($clipper->fresh(), LegalStatus::Business, '55210055400013');

        $this->assertFalse($clipper->fresh()->isVerifiedBusiness());
        $this->assertTrue($clipper->fresh()->hasPendingSiret());
    }

    #[Test]
    public function a_rejected_siret_tells_the_clipper_why(): void
    {
        Notification::fake();

        $clipper = $this->clipperWithBalance(5_000);
        $service = app(LegalStatusService::class);
        $service->declare($clipper, LegalStatus::Business, self::VALID_SIRET);

        $service->reject($clipper->fresh(), 'Établissement fermé');

        $this->assertFalse($clipper->fresh()->hasPendingSiret());
        Notification::assertSentTo($clipper, SiretReviewed::class, fn ($n) => ! $n->accepted);

        $this->actingAs($clipper->fresh())
            ->get(route('payout-method.edit'))
            ->assertSee('Établissement fermé');
    }

    #[Test]
    public function the_clipper_declares_a_siret_from_the_payout_page(): void
    {
        $clipper = $this->clipperWithBalance(5_000);

        $this->actingAs($clipper)
            ->get(route('payout-method.edit'))
            ->assertSuccessful()
            ->assertSee('Statut légal')
            ->assertSee('/ 100,00 €');

        $this->actingAs($clipper)
            ->patch(route('legal-status.update'), ['legal_status' => 'business', 'siret' => '12345678901234'])
            ->assertSessionHasErrorsIn('legalStatus', 'siret');

        $this->actingAs($clipper)
            ->patch(route('legal-status.update'), ['legal_status' => 'business', 'siret' => '732 829 320 00074'])
            ->assertRedirect(route('payout-method.edit'));

        $this->assertSame(self::VALID_SIRET, $clipper->fresh()->siret);
        $this->assertTrue($clipper->fresh()->hasPendingSiret());
    }
}
