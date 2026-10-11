<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Command;

/**
 * Efface les fichiers arrivés à expiration.
 *
 * Ce n'est pas du ménage de confort : le disque est partagé avec MySQL et les
 * journaux. Un espace de travail qui ne se vide jamais finit par arrêter le
 * site entier — et la panne ne ressemblera pas à un problème de stockage, elle
 * ressemblera à une base de données qui refuse d'écrire.
 *
 * Les lignes en échec partent aussi : elles occupent une place dans
 * l'affichage sans porter de fichier, et leur message n'a plus d'intérêt au
 * bout d'une semaine.
 */
class PurgeMediaCommand extends Command
{
    protected $signature = 'media:purge {--dry-run : Montre ce qui serait supprimé}';

    protected $description = 'Efface les fichiers de travail arrivés à expiration.';

    public function handle(): int
    {
        $expires = MediaAsset::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $echecs = MediaAsset::query()
            ->where('status', MediaAsset::ECHEC)
            ->where('updated_at', '<=', now()->subWeek())
            ->get();

        $tout = $expires->merge($echecs);

        if ($tout->isEmpty()) {
            $this->info('Rien à purger.');

            return self::SUCCESS;
        }

        $octets = (int) $tout->sum('bytes');

        if ($this->option('dry-run')) {
            $this->table(
                ['id', 'clippeur', 'type', 'libellé', 'taille', 'expire le'],
                $tout->map(fn (MediaAsset $a) => [
                    $a->getKey(), $a->user_id, $a->kind,
                    mb_substr($a->label, 0, 40), $a->tailleLisible(),
                    $a->expires_at?->format('d/m/Y') ?? '—',
                ])->all(),
            );

            $this->warn('Rien n\'a été supprimé.');

            return self::SUCCESS;
        }

        foreach ($tout as $asset) {
            $asset->supprimerAvecFichier();
        }

        $this->info($tout->count().' fichier(s) supprimé(s), '
            .number_format($octets / 1024 / 1024, 1, ',', ' ').' Mo libérés.');

        return self::SUCCESS;
    }
}
