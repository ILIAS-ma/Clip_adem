<?php

namespace App\Jobs;

use App\Exceptions\MediaFailed;
use App\Models\MediaAsset;
use App\Services\Media\Downloader;
use App\Services\Media\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Récupère le mp4 d'un clip repéré, en arrière-plan.
 *
 * En file d'attente et non dans la requête : un clip de soixante secondes
 * met cinq à trente secondes à arriver, et une requête HTTP qui attend ça
 * occupe un processus PHP-FPM pendant tout ce temps. Quatre clippeurs qui
 * téléchargent en même temps suffiraient à ce que le site ne réponde plus.
 */
class DownloadSourceClip implements ShouldQueue
{
    use Queueable;

    /**
     * Deux essais, pas plus.
     *
     * Les échecs de téléchargement sont rarement passagers : un clip
     * supprimé, privé, ou trop gros le restera. Réessayer dix fois occupe la
     * file et fait attendre les travaux des autres pour rien.
     */
    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public MediaAsset $asset) {}

    /**
     * Un téléchargement à la fois par clippeur.
     *
     * Sans cela, mettre dix clips de côté puis tout télécharger lancerait dix
     * processus yt-dlp simultanés pour une seule personne, et la bande
     * passante du serveur serait à elle. Le verrou expire pour ne pas rester
     * posé si le travail meurt.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('media:'.$this->asset->user_id))
                ->expireAfter(600)
                ->releaseAfter(10),
        ];
    }

    public function handle(Downloader $downloader): void
    {
        $asset = $this->asset->fresh();

        // La ligne a pu être supprimée entre la mise en file et l'exécution.
        if ($asset === null || ! $asset->enTravail()) {
            return;
        }

        $espace = Workspace::for($asset->user);
        $plafond = min(
            (int) config('clipping.media.max_source_mo') * 1024 * 1024,
            $espace->resteOctets(),
        );

        if ($plafond <= 0) {
            $this->echouer($asset, MediaFailed::quotaAtteint());

            return;
        }

        $asset->update(['status' => MediaAsset::EN_COURS, 'error' => null]);

        $espace->preparer('sources');
        $nom = Workspace::nomDeFichier($asset->label);
        $absolu = $espace->chemin('sources/'.$nom);

        try {
            $octets = $downloader->recuperer($asset->bookmark?->url ?? '', $absolu, $plafond);
        } catch (MediaFailed $e) {
            $this->echouer($asset, $e);

            return;
        }

        $asset->update([
            'path' => $espace->dossier('sources/'.$nom),
            'bytes' => $octets,
            'duration_seconds' => $downloader->duree($absolu),
            'status' => MediaAsset::PRET,
            'error' => null,
            // La purge est le seul mécanisme qui garde le disque viable : un
            // fichier sans date d'expiration y resterait pour toujours.
            'expires_at' => now()->addDays((int) config('clipping.media.purge_days')),
        ]);
    }

    /** L'échec est écrit sur la ligne, pour que l'écran puisse l'expliquer. */
    protected function echouer(MediaAsset $asset, MediaFailed $e): void
    {
        Log::warning('Téléchargement de source échoué', [
            'asset_id' => $asset->getKey(),
            'user_id' => $asset->user_id,
            'detail' => $e->detail,
        ]);

        $asset->update([
            'status' => MediaAsset::ECHEC,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Le travail est mort sans passer par `handle` — délai dépassé, processus
     * tué. Sans ce crochet, la ligne resterait « en cours » indéfiniment et
     * l'écran afficherait une attente qui ne finit jamais.
     */
    public function failed(?\Throwable $e): void
    {
        $this->asset->fresh()?->update([
            'status' => MediaAsset::ECHEC,
            'error' => 'Le téléchargement a été interrompu. Réessayez.',
        ]);
    }
}
