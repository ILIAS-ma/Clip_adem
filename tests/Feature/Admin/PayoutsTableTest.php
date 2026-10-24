<?php

namespace Tests\Feature\Admin;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Models\Payout;
use App\Models\User;
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

    #[Test]
    public function the_paypal_email_has_its_own_copyable_column(): void
    {
        $paypal = $this->paypalPayout();
        $bank = Payout::factory()->create([
            'status' => PayoutStatus::Approved,
            'method' => PayoutMethod::BankTransfer,
            'paypal_email' => null,
            'destination' => 'FR76 •••• 1234',
        ]);

        Livewire::test(ListPayouts::class)
            ->set('activeTab', 'approved')
            ->assertCanSeeTableRecords([$paypal, $bank])
            ->assertSee('Email PayPal')
            ->assertSee('adam.paypal@example.com')
            ->assertTableColumnStateSet('paypal_email', 'adam.paypal@example.com', $paypal)
            ->assertTableColumnStateSet('paypal_email', null, $bank);
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
