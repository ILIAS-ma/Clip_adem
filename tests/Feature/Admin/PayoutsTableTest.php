<?php

namespace Tests\Feature\Admin;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Models\Payout;
use App\Models\User;
use App\Support\Iban;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La liste des retraits : l'adresse PayPal se lit et se copie d'un clic, et
 * « PayPal envoyé » n'ouvre aucune fenêtre.
 */
class PayoutsTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['clipping.payouts.paypal_automatic' => false]);

        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]));
    }

    protected function paypalPayout(array $attributes = []): Payout
    {
        return Payout::factory()->create(array_merge([
            'status' => PayoutStatus::Approved,
            'method' => PayoutMethod::PayPal,
            'paypal_email' => 'adam.paypal@example.com',
            'destination' => null,
        ], $attributes));
    }

    protected function bankPayout(string $iban = 'FR7630006000011234567890189', ?string $maskedAsAsked = null): Payout
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);
        $clipper->forceFill(['iban' => $iban, 'account_holder' => 'Adam Chehlaoui', 'bic' => 'AGRIFRPP'])->save();

        return Payout::factory()->create([
            'user_id' => $clipper->id,
            'status' => PayoutStatus::Approved,
            'method' => PayoutMethod::BankTransfer,
            'paypal_email' => null,
            'destination' => $maskedAsAsked ?? Iban::mask($iban),
        ]);
    }

    #[Test]
    public function the_destination_column_shows_the_paypal_email_to_copy(): void
    {
        $paypal = $this->paypalPayout();

        Livewire::test(ListPayouts::class)
            ->set('activeTab', 'approved')
            ->assertCanSeeTableRecords([$paypal])
            ->assertSee('Email PayPal / IBAN')
            ->assertTableColumnStateSet('payment_destination', 'adam.paypal@example.com', $paypal);
    }

    #[Test]
    public function the_super_admin_sees_the_full_iban_with_holder_and_bic_to_copy(): void
    {
        $bank = $this->bankPayout();

        Livewire::test(ListPayouts::class)
            ->set('activeTab', 'approved')
            ->assertTableColumnStateSet('payment_destination', 'FR76 3000 6000 0112 3456 7890 189', $bank)
            ->assertSee('Titulaire : Adam Chehlaoui')
            ->assertSee('BIC : AGRIFRPP');
    }

    #[Test]
    public function a_moderator_only_sees_the_masked_iban(): void
    {
        $bank = $this->bankPayout();

        $this->actingAs(User::factory()->create([
            'role' => UserRole::Moderator,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]));

        Livewire::test(ListPayouts::class)
            ->set('activeTab', 'approved')
            ->assertTableColumnStateSet('payment_destination', '•••• •••• 0189', $bank)
            ->assertDontSee('FR76 3000')
            ->assertDontSee('FR7630006000011234567890189')
            ->assertDontSee('AGRIFRPP');
    }

    #[Test]
    public function an_iban_changed_since_the_request_is_not_offered_for_copy(): void
    {
        // La demande portait un compte en …0189 ; le profil montre maintenant un autre RIB.
        $bank = $this->bankPayout('FR1420041010050500013M02606', '•••• •••• 0189');

        Livewire::test(ListPayouts::class)
            ->set('activeTab', 'approved')
            ->assertTableColumnStateSet('payment_destination', '•••• •••• 0189', $bank)
            ->assertSee('IBAN modifié depuis la demande')
            ->assertDontSee('FR14 2004');
    }

    #[Test]
    public function a_payout_can_be_found_by_its_paypal_email(): void
    {
        $adam = $this->paypalPayout(['paypal_email' => 'adam.paypal@example.com']);
        $other = $this->paypalPayout(['paypal_email' => 'autre.personne@example.com']);

        Livewire::test(ListPayouts::class)
            ->set('activeTab', 'approved')
            ->searchTable('adam.paypal')
            ->assertCanSeeTableRecords([$adam])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function paypal_sent_is_a_single_click_with_no_form(): void
    {
        $payout = $this->paypalPayout();

        // Aucune donnée fournie : pas de référence demandée, le retrait est versé.
        Livewire::test(ListPayouts::class)
            ->set('activeTab', 'approved')
            ->assertTableActionVisible('markPaid', $payout)
            ->callTableAction('markPaid', $payout)
            ->assertHasNoTableActionErrors();

        $payout->refresh();

        $this->assertSame(PayoutStatus::Paid, $payout->status);
        $this->assertNotNull($payout->processed_at);
    }

    #[Test]
    public function a_bank_transfer_still_asks_for_its_optional_reference(): void
    {
        $bank = Payout::factory()->create([
            'status' => PayoutStatus::Approved,
            'method' => PayoutMethod::BankTransfer,
            'paypal_email' => null,
            'destination' => 'FR76 •••• 1234',
        ]);

        Livewire::test(ListPayouts::class)
            ->set('activeTab', 'approved')
            ->callTableAction('markPaid', $bank, ['reference' => 'VIR-20261024-1'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(PayoutStatus::Paid, $bank->fresh()->status);
        $this->assertSame('VIR-20261024-1', $bank->fresh()->paypal_payout_item_id);
    }
}
