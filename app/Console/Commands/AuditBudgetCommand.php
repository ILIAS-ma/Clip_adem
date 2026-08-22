<?php

namespace App\Console\Commands;

use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use Illuminate\Console\Command;

/**
 * Vérifie que le cache dénormalisé n'a pas divergé du grand livre.
 *
 * À planifier quotidiennement en production : c'est le filet qui rattrape une
 * écriture directe faite par erreur en dehors du service.
 */
class AuditBudgetCommand extends Command
{
    protected $signature = 'budget:audit {--fix : Recale spent_cents sur le grand livre}';

    protected $description = 'Contrôle la cohérence des budgets de campagne avec le grand livre.';

    public function handle(): int
    {
        $anomalies = 0;

        Campaign::query()->withTrashed()->chunkById(100, function ($campaigns) use (&$anomalies) {
            foreach ($campaigns as $campaign) {
                $anomalies += $this->auditCampaign($campaign);
            }
        });

        if ($anomalies === 0) {
            $this->info('Aucune anomalie : tous les budgets sont cohérents avec le grand livre.');

            return self::SUCCESS;
        }

        $this->warn("{$anomalies} anomalie(s) détectée(s).");

        return $this->option('fix') ? self::SUCCESS : self::FAILURE;
    }

    protected function auditCampaign(Campaign $campaign): int
    {
        $anomalies = 0;

        $ledger = (int) BudgetTransaction::where('campaign_id', $campaign->getKey())->sum('amount_cents');

        if ($ledger !== $campaign->spent_cents) {
            $anomalies++;
            $this->error(sprintf(
                '[#%d %s] spent_cents=%d mais grand livre=%d (écart %+d centimes).',
                $campaign->getKey(),
                $campaign->title,
                $campaign->spent_cents,
                $ledger,
                $ledger - $campaign->spent_cents,
            ));

            if ($this->option('fix')) {
                $campaign->forceFill(['spent_cents' => max(0, $ledger)])->save();
                $this->line('  → recalé sur le grand livre.');
            }
        }

        if ($campaign->spent_cents > $campaign->budget_total_cents) {
            $anomalies++;
            $this->error(sprintf(
                '[#%d %s] DÉPASSEMENT : %d centimes dépensés pour un budget de %d.',
                $campaign->getKey(),
                $campaign->title,
                $campaign->spent_cents,
                $campaign->budget_total_cents,
            ));
        }

        // La somme des gains des clips doit égaler ce que la campagne a dépensé.
        $clipsEarned = (int) Clip::where('campaign_id', $campaign->getKey())->sum('earned_cents');

        if ($clipsEarned !== $campaign->spent_cents) {
            $anomalies++;
            $this->error(sprintf(
                '[#%d %s] somme des clips=%d mais spent_cents=%d.',
                $campaign->getKey(),
                $campaign->title,
                $clipsEarned,
                $campaign->spent_cents,
            ));
        }

        return $anomalies;
    }
}
