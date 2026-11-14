<?php

namespace Tests\Feature\Payouts;

use App\Enums\ClipStatus;
use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Payouts\PayoutRequestExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayoutRequestExportTest extends TestCase
{
    use RefreshDatabase;

    protected PayoutRequestExport $export;

    protected int $rang = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->export = app(PayoutRequestExport::class);
    }

    /** @return list<array<int, string>> */
    protected function rows(?string $from = null, ?string $to = null, bool $withFullIban = false): array
    {
        $path = storage_path('framework/testing/retraits-'.uniqid().'.csv');

        $this->export->toFile(
            $path,
            $from ? now()->parse($from)->startOfDay() : null,
            $to ? now()->parse($to)->endOfDay() : null,
            $withFullIban,
        );

        $csv = ltrim(file_get_contents($path), "\xEF\xBB\xBF");
        unlink($path);

        return array_map(
            fn (string $line) => str_getcsv($line, ';', '"', ''),
            array_filter(explode("\n", trim($csv))),
        );
    }

    protected function clipper(array $attributes = []): User
    {
        // Pseudo et téléphone sont uniques en base : un rang suffit à
        // distinguer les clippeurs d'un test qui en crée plusieurs. Il repart
        // de zéro à chaque test, donc le premier s'appelle toujours « momo ».
        $this->rang++;

        return User::factory()->create([
            'role' => UserRole::Clipper,
            'pseudo' => 'momo'.($this->rang > 1 ? $this->rang : ''),
            'phone' => '06123456'.str_pad((string) $this->rang, 2, '0', STR_PAD_LEFT),
            ...$attributes,
        ]);
    }

    #[Test]
    public function it_reproduces_the_columns_of_the_payment_form(): void
    {
        $this->assertSame([
            'Horodateur',
            'Pseudo + numéro',
            '@ du compte / des comptes',
            'Nombre de clips posté',
            'Nombre de vues combiné',
            'Moyen de paiement',
            '(PayPal, RIB, autre) à stipuler',
            'Montant EUR',
            'Statut',
        ], $this->rows()[0]);
    }

    #[Test]
    public function a_request_becomes_one_line_with_its_clipper_accounts_clips_and_views(): void
    {
        $clipper = $this->clipper();

        SocialAccount::factory()->create(['user_id' => $clipper->id, 'handle' => 'momo_rocma']);
        SocialAccount::factory()->create(['user_id' => $clipper->id, 'handle' => 'momo2']);

        Clip::factory()->count(3)->create([
            'user_id' => $clipper->id,
            'status' => ClipStatus::Approved,
            'views_total' => 500_000,
            'submitted_at' => now()->subDay(),
        ]);

        Payout::factory()->create([
            'user_id' => $clipper->id,
            'amount_cents' => 12_345,
            'status' => PayoutStatus::Requested,
            'method' => PayoutMethod::PayPal,
            'paypal_email' => 'momo@example.test',
            'requested_at' => now()->setTime(15, 9, 59),
        ]);

        $row = $this->rows()[1];

        $this->assertSame(now()->setTime(15, 9, 59)->format('d/m/Y H:i:s'), $row[0]);
        $this->assertSame('momo + 0612345601', $row[1]);
        $this->assertSame('@momo_rocma / @momo2', $row[2]);
        $this->assertSame('3', $row[3]);
        $this->assertSame('1500000', $row[4]);
        $this->assertSame('Paypal', $row[5]);
        $this->assertSame('momo@example.test', $row[6]);
        $this->assertSame('123,45', $row[7]);
        $this->assertSame('Demandé', $row[8]);
    }

    #[Test]
    public function rejected_and_invalidated_clips_are_left_out_of_the_counts(): void
    {
        $clipper = $this->clipper();

        Clip::factory()->create([
            'user_id' => $clipper->id,
            'status' => ClipStatus::Approved,
            'views_total' => 1_000,
            'submitted_at' => now(),
        ]);

        foreach ([ClipStatus::Rejected, ClipStatus::Invalidated] as $status) {
            Clip::factory()->create([
                'user_id' => $clipper->id,
                'status' => $status,
                'views_total' => 900_000,
                'submitted_at' => now(),
            ]);
        }

        Payout::factory()->create(['user_id' => $clipper->id, 'requested_at' => now()]);

        $row = $this->rows()[1];

        $this->assertSame('1', $row[3]);
        $this->assertSame('1000', $row[4]);
    }

    #[Test]
    public function the_period_selects_the_requests_and_the_clips_of_those_days(): void
    {
        $clipper = $this->clipper();

        // Dans la période.
        Clip::factory()->create([
            'user_id' => $clipper->id,
            'views_total' => 1_000,
            'submitted_at' => now()->parse('2026-10-22 10:00'),
        ]);

        // Hors période : le clip ne doit pas gonfler la ligne.
        Clip::factory()->create([
            'user_id' => $clipper->id,
            'views_total' => 777_000,
            'submitted_at' => now()->parse('2026-10-02 10:00'),
        ]);

        Payout::factory()->create([
            'user_id' => $clipper->id,
            'requested_at' => now()->parse('2026-10-23 09:00'),
        ]);

        // Demande antérieure : hors de la feuille.
        Payout::factory()->create([
            'user_id' => $clipper->id,
            'requested_at' => now()->parse('2026-10-05 09:00'),
        ]);

        $rows = $this->rows('2026-10-19', '2026-10-26');

        $this->assertCount(2, $rows, 'un en-tête et une seule demande');
        $this->assertSame('1', $rows[1][3]);
        $this->assertSame('1000', $rows[1][4]);
    }

    #[Test]
    public function a_bank_transfer_shows_rib_and_hides_the_iban_from_a_moderator(): void
    {
        $iban = 'FR7616906030088706450280313';

        $clipper = $this->clipper([
            'iban' => $iban,
            'bic' => 'AGRIFRPP',
            'account_holder' => 'Momo Rocma',
        ]);

        Payout::factory()->create([
            'user_id' => $clipper->id,
            'method' => PayoutMethod::BankTransfer,
            'destination' => '•••• 0313',
            'requested_at' => now(),
        ]);

        $moderateur = $this->rows()[1];

        $this->assertSame('RIB', $moderateur[5]);
        $this->assertStringNotContainsString('7616906030', $moderateur[6]);

        $superAdmin = $this->rows(withFullIban: true)[1];

        $this->assertStringContainsString('FR76 1690', $superAdmin[6]);
        $this->assertStringContainsString('AGRIFRPP', $superAdmin[6]);
        $this->assertStringContainsString('Momo Rocma', $superAdmin[6]);
    }

    /**
     * Un RIB changé entre la demande et le paiement enverrait l'argent sur un
     * compte que le clippeur n'a pas désigné : la feuille doit le dire plutôt
     * que de proposer le nouveau numéro.
     */
    #[Test]
    public function an_iban_changed_since_the_request_is_flagged_instead_of_exported(): void
    {
        $clipper = $this->clipper(['iban' => 'FR7616906030088706450289999']);

        Payout::factory()->create([
            'user_id' => $clipper->id,
            'method' => PayoutMethod::BankTransfer,
            'destination' => '•••• 0313',
            'requested_at' => now(),
        ]);

        $this->assertStringContainsString(
            'RIB modifié depuis la demande',
            $this->rows(withFullIban: true)[1][6],
        );
    }

    #[Test]
    public function requests_come_out_in_the_order_they_arrived(): void
    {
        foreach (['2026-10-28 20:59', '2026-10-27 15:09', '2026-10-28 10:44'] as $moment) {
            Payout::factory()->create([
                'user_id' => $this->clipper()->id,
                'requested_at' => now()->parse($moment),
            ]);
        }

        $horodateurs = array_column(array_slice($this->rows(), 1), 0);

        $this->assertSame([
            '27/10/2026 15:09:00',
            '28/10/2026 10:44:00',
            '28/10/2026 20:59:00',
        ], $horodateurs);
    }

    #[Test]
    public function the_file_opens_in_a_french_spreadsheet(): void
    {
        Payout::factory()->create([
            'user_id' => $this->clipper()->id,
            'amount_cents' => 1_019_000,
            'requested_at' => now(),
        ]);

        $path = storage_path('framework/testing/retraits-'.uniqid().'.csv');
        $this->export->toFile($path);
        $csv = file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'sans BOM, Excel casse les accents');
        $this->assertStringContainsString(';', explode("\n", $csv)[0]);
        $this->assertStringContainsString('10190,00', $csv, 'virgule décimale');
    }
}
