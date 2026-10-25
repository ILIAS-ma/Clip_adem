<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Services\Moderation\ClipModerationService;
use Illuminate\Console\Command;

/**
 * Refuse les clips validés publiés hors de la campagne, et signale leurs auteurs.
 *
 * Le premier relevé de vues le fait déjà pour chaque nouveau clip ; cette commande
 * rattrape ceux qui ont été validés avant la règle, ou avant que leur date de
 * publication soit connue. Elle montre ce qu'elle va faire avant de le faire.
 */
class EnforcePostingWindowCommand extends Command
{
    protected $signature = 'clips:enforce-window
        {--campaign= : Limiter à une campagne (identifiant)}
        {--force : Refuse réellement, au lieu de seulement lister}';

    protected $description = 'Refuse les clips publiés avant le début (ou après la fin) d\'une campagne qui applique la règle.';

    public function handle(ClipModerationService $moderation): int
    {
        $campaigns = Campaign::query()
            ->whereNotIn('status', [CampaignStatus::Draft->value, CampaignStatus::Archived->value])
            ->when($this->option('campaign'), fn ($query, $id) => $query->whereKey($id))
            ->get()
            ->filter(fn (Campaign $campaign) => $campaign->enforcesPostingWindow());

        if ($campaigns->isEmpty()) {
            $this->info('Aucune campagne n\'applique la règle de publication.');

            return self::SUCCESS;
        }

        $found = [];

        foreach ($campaigns as $campaign) {
            $clips = Clip::query()
                ->where('campaign_id', $campaign->getKey())
                ->where('status', ClipStatus::Approved->value)
                ->whereNotNull('posted_at')
                ->with('user:id,name,pseudo')
                ->get();

            foreach ($clips as $clip) {
                if ($reason = $campaign->postingWindowViolation($clip->posted_at)) {
                    $found[] = [$campaign, $clip, $reason];
                }
            }
        }

        if ($found === []) {
            $this->info('Tous les clips validés sont dans la période de leur campagne.');

            return self::SUCCESS;
        }

        $this->table(
            ['campagne', 'clippeur', 'clip', 'publié le', 'début de la campagne'],
            collect($found)->map(fn (array $row) => [
                '#'.$row[0]->getKey(),
                $row[1]->user?->displayName(),
                '#'.$row[1]->getKey(),
                $row[1]->posted_at->format('d/m/Y H:i'),
                $row[0]->starts_at->format('d/m/Y H:i'),
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn(count($found).' clip(s) seraient refusés. Rien n\'a été modifié : relancez avec --force pour agir.');

            return self::SUCCESS;
        }

        foreach ($found as [$campaign, $clip, $reason]) {
            $moderation->reject($clip, $reason, null, ModerationAction::ClipOutsideWindow);
        }

        $this->newLine();
        $this->info(count($found).' clip(s) refusé(s) et signalé(s).');

        return self::SUCCESS;
    }
}
