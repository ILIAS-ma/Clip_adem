<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Services\Moderation\ReuploadScanner;
use Illuminate\Console\Command;

/**
 * Signale les clips qui ressemblent à une vidéo plus ancienne du même compte.
 *
 * Ne refuse rien : un signalement apparaît dans l'administration, l'équipe décide.
 * Chaque clip n'est contrôlé qu'une fois, donc un passage sans nouveau clip ne coûte
 * aucun appel à la plateforme.
 */
class DetectReuploadsCommand extends Command
{
    protected $signature = 'clips:detect-reuploads
        {--campaign= : Limiter à une campagne (identifiant)}
        {--all : Recontrôler aussi les clips déjà contrôlés}';

    protected $description = 'Signale les vidéos qui semblent être la reprise d\'une vidéo plus ancienne du même compte.';

    public function handle(ReuploadScanner $scanner): int
    {
        $clips = Clip::query()
            ->with('socialAccount')
            ->whereIn('status', [ClipStatus::Approved, ClipStatus::PendingReview])
            ->whereNotNull('social_account_id')
            ->when($this->option('campaign'), fn ($q, $id) => $q->where('campaign_id', $id))
            ->whereHas('campaign', fn ($q) => $q->whereNotIn('status', [CampaignStatus::Draft, CampaignStatus::Archived]))
            ->get()
            ->filter(fn (Clip $clip) => $this->option('all') || empty($clip->compliance['reupload_checked_at'] ?? null));

        if ($clips->isEmpty()) {
            $this->info('Aucun clip à contrôler.');

            return self::SUCCESS;
        }

        $rows = [];
        $failed = 0;

        foreach ($clips->groupBy('social_account_id') as $accountId => $group) {
            $account = SocialAccount::find($accountId);

            $result = $account ? $scanner->scan($account, $group) : null;

            if ($result === null) {
                $failed++;

                continue;
            }

            foreach ($result['flagged'] as $hit) {
                $rows[] = [
                    $hit['clip']->campaign_id,
                    $hit['clip']->user?->pseudo ?: $hit['clip']->user?->name,
                    $hit['clip']->getKey(),
                    $hit['clip']->posted_at?->format('d/m H:i'),
                    $hit['original']['posted_at']->format('d/m H:i'),
                    $hit['similarity'].' %',
                ];
            }
        }

        if ($rows !== []) {
            $this->table(['campagne', 'clippeur', 'clip', 'publié le', 'original publié le', 'ressemblance'], $rows);
        }

        $this->info(sprintf('%d clip(s) contrôlé(s), %d reprise(s) probable(s) signalée(s)%s.', $clips->count(), count($rows), $failed ? ", $failed compte(s) illisible(s)" : ''));

        return self::SUCCESS;
    }
}
