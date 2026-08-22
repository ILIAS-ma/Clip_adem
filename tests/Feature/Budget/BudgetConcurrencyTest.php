<?php

namespace Tests\Feature\Budget;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Support\Budget\CreditOutcome;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Tests de concurrence RÉELS.
 *
 * Trois contraintes d'environnement expliquent la mécanique inhabituelle de ce
 * fichier ; les contourner rendrait les tests verts sans rien démontrer :
 *
 *  1. MySQL obligatoire — SQLite n'a pas de verrou de ligne, lockForUpdate()
 *     y est un no-op silencieux.
 *  2. DatabaseTruncation et non RefreshDatabase — la transaction englobante de
 *     RefreshDatabase rendrait les données invisibles aux autres processus.
 *  3. De vrais processus système — pcntl_fork n'existe pas sous Windows, et
 *     des appels successifs dans le même processus ne se croisent jamais.
 *
 * Une barrière de départ (un fichier que tous les processus attendent) garantit
 * qu'ils frappent la base au même instant plutôt qu'en escalier.
 */
class BudgetConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected string $barrier;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Les tests de concurrence exigent MySQL : SQLite ne verrouille pas les lignes.');
        }

        $this->barrier = sys_get_temp_dir().'/clip-budget-barrier-'.uniqid().'.lock';
    }

    protected function tearDown(): void
    {
        @unlink($this->barrier);

        // Ces tests commitent réellement : sans nettoyage explicite, leurs
        // lignes resteraient visibles pour les classes de tests suivantes,
        // qui, elles, travaillent dans une transaction annulée.
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::withoutForeignKeyConstraints(function () {
                foreach (['campaign_budget_transactions', 'clips', 'campaign_rates', 'campaigns', 'artists', 'users'] as $table) {
                    DB::table($table)->truncate();
                }
            });
        }

        parent::tearDown();
    }

    /** Campagne active de 100 €, 1 € pour 1000 vues sur TikTok. */
    protected function campaign(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(array_merge([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 10_000,
            ], $attributes));
    }

    /**
     * Lance les commandes en parallèle, puis lève la barrière.
     *
     * @param  array<int, array{0: Clip, 1: int, 2: string}>  $jobs  [clip, vues, clé]
     * @return Collection<int, array<string, mixed>>
     */
    protected function runConcurrently(array $jobs): Collection
    {
        $php = (new PhpExecutableFinder)->find() ?: 'php';
        $processes = [];

        foreach ($jobs as [$clip, $views, $key]) {
            $process = new Process(
                command: [
                    $php, 'artisan', 'budget:credit-clip',
                    (string) $clip->getKey(),
                    (string) $views,
                    '--key='.$key,
                    '--barrier='.$this->barrier,
                ],
                cwd: base_path(),
                env: [
                    // Les sous-processus doivent viser la base de test, pas la
                    // base de développement pointée par .env.
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => config('database.default'),
                    'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
                ],
                timeout: 120,
            );

            $process->start();
            $processes[] = $process;
        }

        // Tous les processus ont booté Laravel et attendent le fichier.
        usleep(400_000);
        touch($this->barrier);

        return collect($processes)->map(function (Process $process) {
            $process->wait();

            $this->assertTrue(
                $process->isSuccessful(),
                'Un processus concurrent a échoué : '.$process->getErrorOutput().$process->getOutput(),
            );

            $line = trim(collect(explode("\n", trim($process->getOutput())))->last());

            return json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        });
    }

    #[Test]
    public function twenty_simultaneous_credits_never_exceed_the_budget(): void
    {
        $campaign = $this->campaign(); // 100 € de budget

        // 20 clips valant 10 € chacun : 200 € réclamés pour 100 € disponibles.
        $clips = Clip::factory()->count(20)->create([
            'campaign_id' => $campaign->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => 10_000,
        ]);

        $results = $this->runConcurrently(
            $clips->map(fn (Clip $clip) => [$clip, 10_000, "clip:{$clip->id}:snapshot:1"])->all()
        );

        $campaign->refresh();

        // L'invariant central du projet.
        $this->assertSame(10_000, $campaign->spent_cents, 'Le budget a été dépassé ou sous-consommé.');
        $this->assertSame(0, $campaign->remainingCents());

        $paid = $results->filter(fn ($r) => $r['credited_cents'] > 0);
        $refused = $results->filter(fn ($r) => $r['outcome'] === CreditOutcome::NoBudgetLeft->value
            || $r['outcome'] === CreditOutcome::CampaignClosed->value);

        $this->assertCount(10, $paid, 'Exactement dix clips auraient dû être payés.');
        $this->assertCount(10, $refused, 'Les dix autres auraient dû être refusés faute de budget.');

        // Bascule automatique du statut.
        $this->assertSame(CampaignStatus::Exhausted, $campaign->status);
        $this->assertNotNull($campaign->exhausted_at);

        // Le grand livre reste la source de vérité.
        $this->assertSame(
            10_000,
            (int) BudgetTransaction::where('campaign_id', $campaign->getKey())->sum('amount_cents'),
        );
        $this->assertSame(
            10_000,
            (int) Clip::where('campaign_id', $campaign->getKey())->sum('earned_cents'),
        );
    }

    #[Test]
    public function the_clip_that_straddles_the_limit_is_capped_at_the_exact_remainder(): void
    {
        // Reliquat de 3 €, deux clips de 7 € lancés ensemble.
        $campaign = $this->campaign();
        $campaign->forceFill(['spent_cents' => 9_700])->save();

        $clips = Clip::factory()->count(2)->create([
            'campaign_id' => $campaign->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => 70_000,
        ]);

        $results = $this->runConcurrently(
            $clips->map(fn (Clip $clip) => [$clip, 70_000, "clip:{$clip->id}:snapshot:1"])->all()
        );

        $campaign->refresh();

        $this->assertSame(10_000, $campaign->spent_cents);

        // Un seul des deux touche quelque chose, et exactement le reliquat.
        $credited = $results->sum('credited_cents');
        $this->assertSame(300, $credited);

        $capped = $results->firstWhere('outcome', CreditOutcome::Capped->value);
        $this->assertNotNull($capped, 'Le clip servi aurait dû être marqué comme plafonné.');
        $this->assertSame(300, $capped['credited_cents']);
    }

    #[Test]
    public function the_same_idempotency_key_fired_five_times_debits_once(): void
    {
        $campaign = $this->campaign();
        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => 10_000,
        ]);

        // Le cas du webhook rejoué cinq fois en parallèle.
        $results = $this->runConcurrently(array_fill(0, 5, [$clip, 10_000, 'clip:replay:snapshot:1']));

        $campaign->refresh();

        $this->assertSame(1_000, $campaign->spent_cents, 'Le rejeu a débité plusieurs fois.');
        $this->assertSame(1_000, $clip->fresh()->earned_cents);
        $this->assertSame(1, BudgetTransaction::where('campaign_id', $campaign->getKey())->count());

        $this->assertCount(
            4,
            $results->where('outcome', CreditOutcome::AlreadyProcessed->value),
            'Quatre appels sur cinq auraient dû être reconnus comme déjà traités.',
        );
    }

    #[Test]
    public function random_concurrent_credits_keep_the_ledger_consistent(): void
    {
        // Fuzz : montants et vues aléatoires, pour attraper ce que des cas
        // ronds ne révèlent pas (arrondis, plafonds partiels).
        $campaign = $this->campaign(['budget_total_cents' => 7_777]);

        $clips = Clip::factory()->count(15)->create([
            'campaign_id' => $campaign->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
        ]);

        $jobs = $clips->map(function (Clip $clip) {
            $views = random_int(1_337, 23_456);
            $clip->forceFill(['views_total' => $views])->save();

            return [$clip, $views, "clip:{$clip->id}:snapshot:fuzz"];
        })->all();

        $this->runConcurrently($jobs);

        $campaign->refresh();

        $this->assertLessThanOrEqual($campaign->budget_total_cents, $campaign->spent_cents);

        $ledger = (int) BudgetTransaction::where('campaign_id', $campaign->getKey())->sum('amount_cents');
        $this->assertSame($campaign->spent_cents, $ledger, 'Le cache a divergé du grand livre.');

        $this->assertSame(
            (int) Clip::where('campaign_id', $campaign->getKey())->sum('earned_cents'),
            $ledger,
            'La somme des gains des clips ne correspond plus au grand livre.',
        );

        // Chaque clip payé doit avoir des vues payées cohérentes avec son montant.
        foreach (Clip::where('campaign_id', $campaign->getKey())->get() as $clip) {
            $this->assertSame(
                intdiv($clip->paid_views * 100, 1000),
                $clip->earned_cents,
                "Incohérence vues/gains sur le clip #{$clip->id}.",
            );
        }
    }
}
