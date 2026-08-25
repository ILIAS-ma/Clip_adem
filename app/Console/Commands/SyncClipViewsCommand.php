<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Services\Social\ClipSyncService;
use App\Services\Social\SocialProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncClipViewsCommand extends Command
{
    protected $signature = 'clips:sync
        {--platform= : tiktok, youtube ou instagram ; toutes par défaut}
        {--limit= : Nombre maximum de clips par plateforme}';

    protected $description = 'Relève les vues des clips actifs et fait créditer le budget.';

    public function handle(ClipSyncService $sync, SocialProviderManager $providers): int
    {
        $platforms = $this->option('platform')
            ? [Platform::from($this->option('platform'))]
            : Platform::cases();

        $rows = [];

        foreach ($platforms as $platform) {
            $run = $sync->syncPlatform($platform, $this->option('limit') ? (int) $this->option('limit') : null);

            $rows[] = [
                $platform->label().($providers->isSimulated($platform) ? ' (simulé)' : ''),
                $run->clips_synced,
                $run->api_calls,
                $run->quota_used,
                $run->rate_limited ? 'oui' : 'non',
                $run->error ? Str::limit($run->error, 40) : '—',
            ];
        }

        $this->table(['Plateforme', 'Clips', 'Appels', 'Quota', 'Bridé', 'Erreur'], $rows);

        return self::SUCCESS;
    }
}
