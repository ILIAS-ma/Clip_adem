<?php

namespace Tests\Feature\Payouts;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Exceptions\PayoutRefused;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\User;
use App\Services\Payouts\BankTransferExport;
use App\Services\Payouts\PayoutService;
use App\Support\Iban;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankPayoutTest extends TestCase
{
    use RefreshDatabase;

    /** IBAN de test à clé valide, publiés par les banques concernées. */
    private const VALID_FR = 'FR7630006000011234567890189';

    private const VALID_DE = 'DE89370400440532013000';

    protected function clipper(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => fake()->unique()->userName(),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ], $attributes));
    }

    // ------------------------------------------------------------------
    // IBAN
    // ------------------------------------------------------------------

    #[Test]
    public function a_valid_iban_passes_and_a_mistyped_one_does_not(): void
    {
        $this->assertTrue(Iban::isValid(self::VALID_FR));
        $this->assertTrue(Iban::isValid(self::VALID_DE));
        $this->assertTrue(Iban::isValid('FR76 3000 6000 0112 3456 7890 189'), 'Les espaces du RIB doivent être tolérés.');

        // Un chiffre inversé casse la clé de contrôle : c'est exactement la
        // faute de frappe qui coûte un virement rejeté.
        $this->assertFalse(Iban::isValid('FR7630006000011234567890198'));

        // Bonne clé de contrôle possible, mauvaise longueur pour le pays.
        $this->assertFalse(Iban::isValid('FR7630006000011234567890'));

        $this->assertFalse(Iban::isValid('pas un iban'));
        $this->assertFalse(Iban::isValid(null));
    }

    #[Test]
    public function the_form_refuses_an_invalid_iban(): void
    {
        $clipper = $this->clipper();

        $this->actingAs($clipper)
            ->patch(route('payout-method.update'), [
                'payout_method' => PayoutMethod::BankTransfer->value,
                'account_holder' => 'Maya Bernard',
                'iban' => 'FR7630006000011234567890198',
            ])
            ->assertSessionHasErrors('iban');

        $this->assertNull($clipper->fresh()->iban);
    }

    // ------------------------------------------------------------------
    // Enregistrement du moyen de paiement
    // ------------------------------------------------------------------

    #[Test]
    public function a_saved_iban_is_encrypted_and_only_its_last_four_digits_stay_readable(): void
    {
        $clipper = $this->clipper();

        $this->actingAs($clipper)
            ->patch(route('payout-method.update'), [
                'payout_method' => PayoutMethod::BankTransfer->value,
                'account_holder' => 'Maya Bernard',
                'iban' => 'FR76 3000 6000 0112 3456 7890 189',
                'bic' => 'bnpafrpp',
            ])
            ->assertRedirect(route('payout-method.edit'));

        $clipper->refresh();

        $this->assertSame(PayoutMethod::BankTransfer, $clipper->payout_method);
        $this->assertSame(self::VALID_FR, $clipper->iban, 'L\'IBAN doit être normalisé sans espaces.');
        $this->assertSame('0189', $clipper->iban_last4);
        $this->assertSame('BNPAFRPP', $clipper->bic);

        // Un dump de base ne doit pas livrer l'IBAN en clair.
        $stored = \DB::table('users')->where('id', $clipper->getKey())->value('iban');
        $this->assertNotSame(self::VALID_FR, $stored);
        $this->assertStringNotContainsString('1234567890189', (string) $stored);
    }

    #[Test]
    public function switching_back_to_paypal_wipes_the_bank_details(): void
    {
        $clipper = $this->clipper();

        $this->actingAs($clipper)->patch(route('payout-method.update'), [
            'payout_method' => PayoutMethod::BankTransfer->value,
            'account_holder' => 'Maya Bernard',
            'iban' => self::VALID_FR,
        ]);

        $this->actingAs($clipper)->patch(route('payout-method.update'), [
            'payout_method' => PayoutMethod::PayPal->value,
            'paypal_email' => 'maya@paypal.test',
        ]);

        $clipper->refresh();

        // Garder un IBAN dormant, c'est conserver une donnée bancaire dont
        // plus personne n'a l'usage.
        $this->assertNull($clipper->iban);
        $this->assertNull($clipper->iban_last4);
        $this->assertSame('maya@paypal.test', $clipper->paypal_email);
    }

    #[Test]
    public function the_displayed_destination_never_shows_the_full_iban(): void
    {
        $clipper = $this->clipper([
            'payout_method' => PayoutMethod::BankTransfer,
            'iban' => self::VALID_FR,
            'iban_last4' => '0189',
        ]);

        $label = $clipper->payoutDestinationLabel();

        $this->assertStringContainsString('0189', $label);
        $this->assertStringNotContainsString('3000600001', $label);
    }

    // ------------------------------------------------------------------
    // Cycle de vie d'un retrait par virement
    // ------------------------------------------------------------------

    #[Test]
    public function a_clipper_without_any_destination_cannot_request_a_payout(): void
    {
        $clipper = $this->clipper(['paypal_email' => null]);

        $this->expectException(PayoutRefused::class);
        $this->expectExceptionMessage('adresse PayPal');

        app(PayoutService::class)->request($clipper, 2_000);
    }

    #[Test]
    public function a_bank_payout_freezes_its_masked_destination(): void
    {
        $clipper = $this->clipper([
            'payout_method' => PayoutMethod::BankTransfer,
            'iban' => self::VALID_FR,
            'iban_last4' => '0189',
        ]);

        $this->giveBalance($clipper, 10_000);

        $payout = app(PayoutService::class)->request($clipper, 6_000);

        $this->assertSame(PayoutMethod::BankTransfer, $payout->method);
        $this->assertStringContainsString('0189', $payout->destination);
        $this->assertNull($payout->paypal_email, 'Un virement bancaire n\'a pas d\'adresse PayPal.');

        // Le clippeur change de banque : l'historique ne doit pas bouger.
        $clipper->forceFill(['iban' => self::VALID_DE, 'iban_last4' => '3000'])->save();

        $this->assertStringContainsString('0189', $payout->fresh()->destination);
    }

    #[Test]
    public function a_bank_payout_is_never_swept_into_a_paypal_batch(): void
    {
        $bank = $this->bankPayout(PayoutStatus::Approved);
        $paypal = Payout::factory()->create([
            'status' => PayoutStatus::Approved,
            'method' => PayoutMethod::PayPal,
        ]);

        $pending = app(BankTransferExport::class)->pending();

        $this->assertTrue($pending->contains('id', $bank->getKey()));
        $this->assertFalse($pending->contains('id', $paypal->getKey()));
    }

    #[Test]
    public function marking_a_transfer_as_paid_settles_it_and_records_who_did_it(): void
    {
        $payout = $this->bankPayout(PayoutStatus::Approved);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        app(PayoutService::class)->markPaid($payout, $admin, 'VIR-20260904-77');

        $payout->refresh();

        $this->assertSame(PayoutStatus::Paid, $payout->status);
        $this->assertNotNull($payout->processed_at);
        $this->assertSame('VIR-20260904-77', $payout->paypal_payout_item_id);
    }

    #[Test]
    public function a_paypal_payout_cannot_be_settled_by_hand(): void
    {
        // Sinon un administrateur pourrait déclarer versé un retrait que PayPal
        // n'a jamais envoyé, et le solde du clippeur disparaîtrait.
        $payout = Payout::factory()->create([
            'status' => PayoutStatus::Approved,
            'method' => PayoutMethod::PayPal,
        ]);

        $this->expectException(PayoutRefused::class);

        app(PayoutService::class)->markPaid($payout);
    }

    #[Test]
    public function a_transfer_that_is_not_approved_yet_cannot_be_settled(): void
    {
        $payout = $this->bankPayout(PayoutStatus::Requested);

        $this->expectException(PayoutRefused::class);
        $this->expectExceptionMessage('validé');

        app(PayoutService::class)->markPaid($payout);
    }

    // ------------------------------------------------------------------

    /** Donne du solde retirable en créditant un clip réel. */
    protected function giveBalance(User $clipper, int $cents): void
    {
        Clip::factory()->create([
            'user_id' => $clipper->getKey(),
            'earned_cents' => $cents,
        ]);
    }

    protected function bankPayout(PayoutStatus $status): Payout
    {
        $clipper = $this->clipper([
            'payout_method' => PayoutMethod::BankTransfer,
            'iban' => self::VALID_FR,
            'iban_last4' => '0189',
            'account_holder' => 'Maya Bernard',
        ]);

        return Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'status' => $status,
            'method' => PayoutMethod::BankTransfer,
            'destination' => Iban::mask(self::VALID_FR),
            'paypal_email' => null,
        ]);
    }
}
