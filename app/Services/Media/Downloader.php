<?php

namespace App\Services\Media;

use App\Exceptions\MediaFailed;
use Illuminate\Support\Facades\Process;

/**
 * Récupère le mp4 d'un clip, par yt-dlp.
 *
 * Pourquoi un outil externe et non une requête directe : Twitch a migré ses
 * vignettes vers un domaine qui n'encode plus le chemin du mp4 source.
 * L'ancienne déduction à partir de l'URL de vignette ne marche plus que sur
 * les clips anciens, et rien ne distingue les deux cas à l'œil. yt-dlp suit
 * ces changements, nous non.
 *
 * L'URL n'est jamais interpolée dans une ligne de commande : `Process::run`
 * reçoit un tableau d'arguments, donc aucun shell ne la relit. Une URL est
 * une chaîne fournie par un tiers — une qui contiendrait `; rm -rf` n'est pas
 * une hypothèse d'école.
 */
class Downloader
{
    /** Les domaines dont on accepte de télécharger. */
    public const HOTES = ['twitch.tv', 'clips.twitch.tv', 'www.twitch.tv', 'kick.com', 'www.kick.com'];

    public function estDisponible(): bool
    {
        return $this->binaire() !== null;
    }

    /**
     * Télécharge l'URL vers le fichier indiqué.
     *
     * @return int Taille du fichier obtenu, en octets.
     *
     * @throws MediaFailed
     */
    public function recuperer(string $url, string $destination, int $maxOctets): int
    {
        /*
         * L'URL est jugée avant qu'on cherche l'outil : une adresse refusée
         * est un problème d'entrée, et le dire ainsi reste vrai sur une
         * machine où yt-dlp n'est pas installé — un poste de développement,
         * par exemple. Dans l'autre ordre, le message parlait du serveur là
         * où le défaut venait de la demande.
         */
        $this->refuseUrlEtrangere($url);

        $binaire = $this->binaire();

        if ($binaire === null) {
            throw MediaFailed::outilAbsent('yt-dlp');
        }

        /*
         * `--max-filesize` est une ceinture côté outil, le quota en est une
         * autre côté base : yt-dlp s'arrête avant d'écrire un fichier trop
         * gros, ce qui évite de remplir le disque pour découvrir ensuite
         * qu'on dépassait le quota.
         *
         * `--no-playlist` : une URL de chaîne, et non de clip, ferait sinon
         * descendre tout l'historique.
         */
        $resultat = Process::timeout((int) config('clipping.media.download_timeout', 240))
            ->run([
                $binaire,
                '--no-playlist',
                '--no-progress',
                '--no-warnings',
                '--max-filesize', (string) $maxOctets,
                '--format', 'mp4/best[ext=mp4]/best',
                '--output', $destination,
                $url,
            ]);

        if (! $resultat->successful()) {
            throw MediaFailed::telechargementRefuse($resultat->errorOutput() ?: $resultat->output());
        }

        if (! is_file($destination)) {
            // yt-dlp sort en succès quand il renonce pour cause de taille :
            // sans ce contrôle, on enregistrerait un fichier inexistant.
            throw MediaFailed::fichierAbsent();
        }

        return (int) filesize($destination);
    }

    /**
     * Durée du fichier, par ffprobe. Null si indéterminable.
     *
     * Elle sert à l'affichage et au découpage ; une absence ne doit pas faire
     * échouer un téléchargement par ailleurs réussi.
     */
    public function duree(string $fichier): ?int
    {
        $ffprobe = $this->binaire('ffprobe');

        if ($ffprobe === null) {
            return null;
        }

        $resultat = Process::timeout(20)->run([
            $ffprobe, '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $fichier,
        ]);

        if (! $resultat->successful()) {
            return null;
        }

        $secondes = (float) trim($resultat->output());

        return $secondes > 0 ? (int) ceil($secondes) : null;
    }

    /**
     * Refuse une URL qui ne vient pas d'une plateforme attendue.
     *
     * Sans cette liste, l'adresse d'un clip serait un moyen de faire aller
     * notre serveur n'importe où — y compris sur une adresse interne, ce que
     * yt-dlp accepte sans discuter.
     *
     * @throws MediaFailed
     */
    protected function refuseUrlEtrangere(string $url): void
    {
        $hote = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $schema = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($schema !== 'https' || ! in_array($hote, self::HOTES, true)) {
            throw MediaFailed::urlRefusee();
        }
    }

    /** Le binaire, du chemin configuré ou du PATH. */
    protected function binaire(string $nom = 'yt-dlp'): ?string
    {
        $configure = (string) config('clipping.media.'.str_replace('-', '_', $nom).'_path');

        if ($configure !== '' && is_executable($configure)) {
            return $configure;
        }

        foreach (['/usr/local/bin/', '/usr/bin/', ''] as $prefixe) {
            $candidat = $prefixe.$nom;

            if ($prefixe !== '' && is_executable($candidat)) {
                return $candidat;
            }
        }

        // Dernier recours : laisser le système chercher dans le PATH.
        $ou = Process::run(['which', $nom]);

        return $ou->successful() && trim($ou->output()) !== ''
            ? trim($ou->output())
            : null;
    }
}
