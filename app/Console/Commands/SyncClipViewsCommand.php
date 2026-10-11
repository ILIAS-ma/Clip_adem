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
        /*
         * Seulement les plateformes réellement utilisables. En production,
         * YouTube et Instagram n'ont pas de clés : les parcourir faisait
         * lever la commande à chaque passage, toutes les cinq minutes. TikTok
         * passant en premier il était relevé quand même — mais un simple
         * changement d'ordre aurait arrêté le relevé, donc les paiements, de
         * tous les clippeurs.
         */
        $platforms = $this->option('platform')
            ? [Platform::from($this->option('platform'))]
            : array_values(array_filter(
                Platform::cases(),
                fn (Platform $p) => $providers->isAvailable($p),
            ));

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
