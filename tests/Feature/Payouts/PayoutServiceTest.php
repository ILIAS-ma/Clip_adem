<?php

namespace Tests\Feature\Payouts;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\PayoutRefused;
use App\Exceptions\PayPalException;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\Payout;
use App\Models\User;
use App\Services\Payouts\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PayoutService $payouts;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paypal.client_id', 'test-client');
        config()->set('services.paypal.client_secret', 'test-secret');

        $this->payouts = app(PayoutService::class);
    }

    /** Crée un clippeur avec un solde réel, gagné via le moteur de budget. */
    protected function clipperWithBalance(int $cents): User
    {
        $clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'paypal_email' => fake()->unique()->safeEmail(),
        ]);

        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => max($cents, 1) * 10,
            ]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
        ]);

        // 1 € pour 1000 vues : le nombre de vues qui vaut exactement $cents.
        app(CampaignBudgetService::class)->creditViews($clip, $cents * 10, "clip:{$clip->id}:snapshot:1");

        return $clipper->fresh();
    }

    protected function fakePayPal(array $itemsStatus = ['SUCCESS']): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            '*/v1/payments/payouts' => Http::response([
                'batch_header' => [
                    'payout_batch_id' => 'BATCH123',
                    'batch_status' => 'PENDING',
                ],
            ], 201),
            // Construit dynamiquement la réponse depuis les retraits réellement
            // partis : les identifiants auto-incrémentés ne repartent pas de 1
            // entre deux tests, un jeu de données figé ne correspondrait à rien.
            '*/v1/payments/payouts/*' => function () use ($itemsStatus) {
                $inFlight = Payout::where('status', PayoutStatus::Processing)->orderBy('id')->get();

                return Http::response([
                    'batch_header' => ['payout_batch_id' => 'BATCH123', 'batch_status' => 'SUCCESS'],
                    'items' => $inFlight->values()->map(fn (Payout $payout, int $index) => [
                        'payout_item_id' => 'ITEM'.$payout->getKey(),
                        'transaction_status' => $itemsStatus[$index] ?? 'SUCCESS',
                        'payout_item' => ['sender_item_id' => (string) $payout->getKey()],
                    ])->all(),
                ]);
            },
        ]);
    }

    #[Test]
    public function a_clipper_can_withdraw_what_they_earned(): void
    {
        $clipper = $this->clipperWithBalance(8_000); // 80 €

        $payout = $this->payouts->request($clipper, 5_000);

        $this->assertSame(5_000, $payout->amount_cents);
        $this->assertSame($clipper->paypal_email, $payout->paypal_email);
        $this->assertSame(3_000, $clipper->fresh()->availableBalanceCents());
    }

    #[Test]
    public function withdrawing_more_than_the_balance_is_refused(): void
    {
        $clipper = $this->clipperWithBalance(2_000);

        $this->expectException(PayoutRefused::class);
        $this->expectExceptionMessage('Solde insuffisant');

        $this->payouts->request($clipper, 5_000);
    }

    #[Test]
    public function two_requests_cannot_spend_the_same_balance_twice(): void
    {
        // Même discipline que le moteur de budget : le solde est vérifié sous
        // verrou, sinon deux demandes concurrentes retirent deux fois.
        $clipper = $this->clipperWithBalance(5_000);

        $this->payouts->request($clipper, 5_000);

        $this->expectException(PayoutRefused::class);

        $this->payouts->request($clipper->fresh(), 5_000);
    }

    #[Test]
    public function a_withdrawal_below_the_minimum_is_refused(): void
    {
        $clipper = $this->clipperWithBalance(5_000);

        $this->expectException(PayoutRefused::class);
        $this->expectExceptionMessage('Retrait minimum');

        $this->payouts->request($clipper, 500);
    }

    #[Test]
    public function a_banned_clipper_cannot_withdraw(): void
    {
        $clipper = $this->clipperWithBalance(5_000);
        $clipper->forceFill(['is_banned' => true])->save();

        $this->expectException(PayoutRefused::class);
        $this->expectExceptionMessage('banni');

        $this->payouts->request($clipper->fresh(), 3_000);
    }

    #[Test]
    public function a_clipper_without_a_paypal_address_cannot_withdraw(): void
    {
        $clipper = $this->clipperWithBalance(5_000);
        $clipper->forceFill(['paypal_email' => null])->save();

        $this->expectException(PayoutRefused::class);
        $this->expectExceptionMessage('adresse PayPal');

        $this->payouts->request($clipper->fresh(), 3_000);
    }

    #[Test]
    public function small_withdrawals_from_a_clean_clipper_are_auto_approved(): void
    {
        $clipper = $this->clipperWithBalance(10_000);

        $small = $this->payouts->request($clipper, 2_000); // < 50 €
        $this->assertSame(PayoutStatus::Approved, $small->status);

        $large = $this->payouts->request($clipper->fresh(), 6_000);
        $this->assertSame(PayoutStatus::Requested, $large->status);
    }

    #[Test]
    public function a_clipper_with_a_moderation_record_is_never_auto_approved(): void
    {
        $clipper = $this->clipperWithBalance(10_000);

        ModerationLog::record(ModerationAction::ClipRejected, $clipper, null, 'Brief non respecté');

        $payout = $this->payouts->request($clipper, 2_000);

        $this->assertSame(PayoutStatus::Requested, $payout->status);
    }

    #[Test]
    public function approving_records_who_decided(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $payout = Payout::factory()->create(['status' => PayoutStatus::Requested]);

        $this->payouts->approve($payout, $admin);

        $payout->refresh();
        $this->assertSame(PayoutStatus::Approved, $payout->status);
        $this->assertSame($admin->getKey(), $payout->approved_by);
        $this->assertNotNull($payout->approved_at);

        $log = ModerationLog::where('action', ModerationAction::PayoutApproved)->sole();
        $this->assertSame($admin->getKey(), $log->user_id);
    }

    #[Test]
    public function a_payout_in_flight_cannot_be_cancelled(): void
    {
        $payout = Payout::factory()->create(['status' => PayoutStatus::Processing]);

        $this->expectException(PayoutRefused::class);
        $this->expectExceptionMessage('attendre le retour de PayPal');

        $this->payouts->cancel($payout, 'Erreur');
    }

    #[Test]
    public function sending_a_batch_marks_payouts_in_flight_before_calling_paypal(): void
    {
        $this->fakePayPal();

        $payout = Payout::factory()->create([
            'status' => PayoutStatus::Approved,
            'amount_cents' => 4_200,
        ]);

        $result = $this->payouts->sendApproved();

        $this->assertSame(1, $result['count']);
        $this->assertSame(4_200, $result['amount_cents']);
        $this->assertSame('BATCH123', $result['batch_id']);

        $payout->refresh();
        $this->assertSame(PayoutStatus::Processing, $payout->status);
        $this->assertSame('BATCH123', $payout->paypal_batch_id);

        // Le montant part en euros décimaux, jamais en centimes.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/payments/payouts')
                && data_get($request->data(), 'items.0.amount.value') === '42.00';
        });
    }

    #[Test]
    public function a_rejected_batch_leaves_payouts_in_flight_for_reconciliation(): void
    {
        // L'appel a pu aboutir malgré l'erreur : remettre les retraits en file
        // risquerait un second virement. C'est payouts:sync qui tranche.
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
            '*/v1/payments/payouts' => Http::response(['name' => 'INTERNAL_SERVER_ERROR'], 500),
        ]);

        $payout = Payout::factory()->create(['status' => PayoutStatus::Approved]);

        try {
            $this->payouts->sendApproved();
            $this->fail('Une PayPalException était attendue.');
        } catch (PayPalException) {
            // attendu
        }

        $payout->refresh();
        $this->assertSame(PayoutStatus::Processing, $payout->status);
        $this->assertStringContainsString('réconcilier', $payout->failure_reason);
    }

    #[Test]
    public function syncing_marks_successful_items_as_paid(): void
    {
        $this->fakePayPal(['SUCCESS']);

        $payout = Payout::factory()->create(['status' => PayoutStatus::Approved]);
        $this->payouts->sendApproved();

        $this->payouts->syncBatch('BATCH123');

        $payout->refresh();
        $this->assertSame(PayoutStatus::Paid, $payout->status);
        $this->assertNotNull($payout->processed_at);
    }

    #[Test]
    public function an_unclaimed_item_frees_the_balance_again(): void
    {
        // PayPal rendra l'argent sous 30 jours : le solde du clippeur redevient
        // disponible tout de suite, sans jamais toucher au budget de campagne.
        $this->fakePayPal(['UNCLAIMED']);

        $clipper = $this->clipperWithBalance(6_000);
        $payout = $this->payouts->request($clipper, 6_000);
        $this->payouts->approve($payout->fresh(), null);
        $this->payouts->sendApproved();

        $this->assertSame(0, $clipper->fresh()->availableBalanceCents());

        $this->payouts->syncBatch('BATCH123');

        $this->assertSame(PayoutStatus::Failed, $payout->fresh()->status);
        $this->assertSame(6_000, $clipper->fresh()->availableBalanceCents());
    }

    #[Test]
    public function an_unknown_batch_puts_the_payouts_back_in_the_queue(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
            '*/v1/payments/payouts/*' => Http::response(['name' => 'RESOURCE_NOT_FOUND'], 404),
        ]);

        $payout = Payout::factory()->create([
            'status' => PayoutStatus::Processing,
            'paypal_batch_id' => 'GHOST',
        ]);

        $this->payouts->syncBatch('GHOST');

        $payout->refresh();
        $this->assertSame(PayoutStatus::Approved, $payout->status);
        $this->assertNull($payout->paypal_batch_id);
    }

    #[Test]
    public function a_payout_never_touches_a_campaign_budget(): void
    {
        $this->fakePayPal(['SUCCESS']);

        $clipper = $this->clipperWithBalance(5_000);
        $campaign = Campaign::first();
        $spentBefore = $campaign->spent_cents;

        $payout = $this->payouts->request($clipper, 5_000);
        $this->payouts->approve($payout->fresh(), null);
        $this->payouts->sendApproved();
        $this->payouts->syncBatch('BATCH123');

        $this->assertSame($spentBefore, $campaign->fresh()->spent_cents);
    }
}
