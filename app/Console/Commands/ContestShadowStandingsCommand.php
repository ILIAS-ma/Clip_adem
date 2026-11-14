<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\Campaigns\ContestShadowStandings;
use Illuminate\Console\Command;

/**
 * Recalcule le classement « avec les vidéos non soumises » des classements en cours.
 *
 * Réservé à la lecture du super-admin : ce classement n'attribue rien. Il lit TikTok,
 * d'où une tâche planifiée plutôt qu'un calcul à l'ouverture de la page.
 */
class ContestShadowStandingsCommand extends Command
{
    protected $signature = 'contests:shadow-standings {--campaign= : Limiter à une campagne (identifiant)}';

    protected $description = 'Calcule le classement qui compterait aussi les vidéos non soumises (lecture réservée au super-admin).';

    public function handle(ContestShadowStandings $shadow): int
    {
        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->when($this->option('campaign'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn (Campaign $campaign) => $campaign->isContest() && ! $campaign->contestIsOver());

        if ($campaigns->isEmpty()) {
            $this->info('Aucun classement en cours.');

            return self::SUCCESS;
        }

        foreach ($campaigns as $campaign) {
            $result = $shadow->refresh($campaign);

            $this->info(sprintf(
                '#%d %s : %d clippeurs aux vues, %d aux clips%s.',
                $campaign->getKey(),
                $campaign->title,
                count($result['views']),
                count($result['posts']),
                $result['unreadable'] ? ", {$result['unreadable']} compte(s) illisible(s)" : '',
            ));
        }

        return self::SUCCESS;
    }
}
