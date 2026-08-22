<?php

namespace App\Console\Commands;

use App\Contracts\CampaignBudgetService;
use App\Models\Clip;
use Illuminate\Console\Command;

class CreditClipCommand extends Command
{
    protected $signature = 'budget:credit-clip
        {clip : Identifiant du clip}
        {views : Nombre total de vues relevé}
        {--key= : Clé d\'idempotence. Par défaut clip:ID:manual:HORODATAGE}
        {--barrier= : Fichier attendu avant de lancer le crédit (tests de concurrence)}
        {--timeout=30 : Attente maximale de la barrière, en secondes}';

    protected $description = 'Crédite les vues d\'un clip via le moteur de budget. Sert aussi de processus isolé aux tests de concurrence.';

    public function handle(CampaignBudgetService $budget): int
    {
        $clip = Clip::find((int) $this->argument('clip'));

        if (! $clip) {
            $this->error('Clip introuvable.');

            return self::FAILURE;
        }

        $views = (int) $this->argument('views');
        $key = $this->option('key') ?: sprintf('clip:%d:manual:%s', $clip->getKey(), now()->format('YmdHisv'));

        // Barrière de départ : tous les processus lancés par un test se mettent
        // en attente du même fichier, puis démarrent ensemble. Sans ça, ils
        // s'exécutent en escalier et ne se croisent jamais vraiment.
        if ($barrier = $this->option('barrier')) {
            $this->waitForBarrier($barrier, (int) $this->option('timeout'));
        }

        $result = $budget->creditViews($clip, $views, $key);

        // Sortie machine : le test parent lit ce JSON pour agréger les issues.
        $this->line(json_encode([
            'outcome' => $result->outcome->value,
            'credited_cents' => $result->creditedCents,
            'credited_views' => $result->creditedViews,
            'remaining_cents' => $result->remainingCents,
            'campaign_exhausted' => $result->campaignExhausted,
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    protected function waitForBarrier(string $path, int $timeoutSeconds): void
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (! file_exists($path)) {
            if (microtime(true) > $deadline) {
                throw new \RuntimeException("Barrière jamais levée : {$path}");
            }

            usleep(2000);
        }
    }
}
