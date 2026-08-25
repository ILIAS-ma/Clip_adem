<?php

namespace Tests\Feature\Accounting;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Payout;
use App\Services\Accounting\AccountingExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingExportTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingExport $export;

    protected function setUp(): void
    {
        parent::setUp();

        $this->export = app(AccountingExport::class);
    }

    protected function spend(int $views = 10_000): Campaign
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, $views, "clip:{$clip->id}:snapshot:1");

        return $campaign->fresh();
    }

    protected function csvPath(string $journal): string
    {
        return storage_path('framework/testing/'.$journal.'-'.uniqid().'.csv');
    }

    #[Test]
    public function the_spendings_journal_lists_every_ledger_entry(): void
    {
        $this->spend();
        $path = $this->csvPath('depenses');

        $result = $this->export->toFile(AccountingExport::SPENDINGS, $path);

        $this->assertSame(1, $result['rows']);

        $content = file_get_contents($path);
        $this->assertStringContainsString('Montant EUR', $content);
        $this->assertStringContainsString('10,00', $content);
        $this->assertStringContainsString('Dépense', $content);

        @unlink($path);
    }

    #[Test]
    public function amounts_use_a_decimal_comma_and_the_file_opens_cleanly_in_excel(): void
    {
        $this->spend(12_345);
        $path = $this->csvPath('depenses');

        $this->export->toFile(AccountingExport::SPENDINGS, $path);
        $content = file_get_contents($path);

        // BOM UTF-8 : sans lui, Excel massacre les accents.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString(';', $content);
        // 12 345 vues à 1 € / 1000 = 1234 centimes, arrondi plancher.
        $this->assertStringContainsString('12,34', $content);

        @unlink($path);
    }

    #[Test]
    public function a_reversal_appears_as_a_negative_line(): void
    {
        $campaign = $this->spend();
        $clip = $campaign->clips()->sole();

        app(CampaignBudgetService::class)->reverseClip($clip, 'Vues achetées');

        $path = $this->csvPath('depenses');
        $result = $this->export->toFile(AccountingExport::SPENDINGS, $path);

        $this->assertSame(2, $result['rows']);
        $this->assertStringContainsString('-10,00', file_get_contents($path));
        $this->assertStringContainsString('Annulation', file_get_contents($path));

        @unlink($path);
    }

    #[Test]
    public function the_payouts_journal_carries_the_paypal_identifiers(): void
    {
        Payout::factory()->create([
            'status' => PayoutStatus::Paid,
            'amount_cents' => 4_200,
            'paypal_batch_id' => 'BATCH123',
            'paypal_payout_item_id' => 'ITEM456',
            'processed_at' => now(),
        ]);

        $path = $this->csvPath('versements');
        $this->export->toFile(AccountingExport::PAYOUTS, $path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('BATCH123', $content);
        $this->assertStringContainsString('ITEM456', $content);
        $this->assertStringContainsString('42,00', $content);

        @unlink($path);
    }

    #[Test]
    public function the_date_range_narrows_the_export(): void
    {
        $this->spend();

        $path = $this->csvPath('depenses');
        $result = $this->export->toFile(
            AccountingExport::SPENDINGS,
            $path,
            now()->subYear()->startOfDay(),
            now()->subMonth()->endOfDay(),
        );

        $this->assertSame(0, $result['rows']);

        @unlink($path);
    }

    #[Test]
    public function the_reconciliation_separates_what_is_spent_from_what_is_paid(): void
    {
        // Le budget est consommé au crédit des vues, l'argent part plus tard :
        // l'écart est ce que la plateforme doit encore aux clippeurs.
        $this->spend(); // 10 € consommés

        Payout::factory()->create([
            'status' => PayoutStatus::Paid,
            'amount_cents' => 400,
        ]);

        $reconciliation = $this->export->reconciliation();

        $this->assertSame(1_000, $reconciliation['spent_cents']);
        $this->assertSame(400, $reconciliation['paid_cents']);
        $this->assertSame(600, $reconciliation['owed_cents']);
    }

    #[Test]
    public function an_unknown_journal_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->export->toFile('inconnu', $this->csvPath('x'));
    }
}
