<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * L'espace de travail d'un clippeur : ses fichiers, son quota.
 *
 * Un dossier par personne, et c'est la décision structurante. L'outil
 * d'origine gardait un seul dossier `downloads/` pour la machine : mis en
 * ligne tel quel, chacun aurait vu — et téléchargé — les fichiers des autres.
 * Dans un système où le budget part au premier arrivé, lire le dossier d'un
 * concurrent revient à lire sa liste de repérage.
 *
 * Le quota n'est pas une politique commerciale, c'est une protection du
 * serveur : soixante-sept gigaoctets libres, un montage vertical pèse dans
 * les vingt mégaoctets, et rien n'empêcherait dix clippeurs de remplir le
 * disque en une soirée — ce qui arrête aussi MySQL, les journaux et le site.
 */
class Workspace
{
    /** Le disque privé : ces fichiers ne sont jamais servis directement. */
    public const DISQUE = 'local';

    public function __construct(protected User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    /** Chemin relatif au disque privé. */
    public function dossier(string $sous = ''): string
    {
        return trim('clippeurs/'.$this->user->getKey().'/'.$sous, '/');
    }

    /** Chemin absolu, pour les outils en ligne de commande. */
    public function chemin(string $sous = ''): string
    {
        return storage_path('app/private/'.$this->dossier($sous));
    }

    public function preparer(string $sous = ''): string
    {
        $chemin = $this->chemin($sous);

        File::ensureDirectoryExists($chemin, 0755);

        return $chemin;
    }

    /** Octets occupés, d'après les fichiers réellement présents. */
    public function occupeOctets(): int
    {
        return (int) MediaAsset::forUser($this->user)
            ->where('status', MediaAsset::PRET)
            ->sum('bytes');
    }

    public function quotaOctets(): int
    {
        return (int) config('clipping.media.quota_mo') * 1024 * 1024;
    }

    public function resteOctets(): int
    {
        return max(0, $this->quotaOctets() - $this->occupeOctets());
    }

    /**
     * Reste-t-il de la place pour un fichier de cette taille ?
     *
     * Vérifié avant de lancer le travail, pas après : un téléchargement qui
     * échoue à l'écriture laisse un fichier tronqué et une ligne en erreur
     * que personne ne comprend.
     */
    public function accepte(int $octets): bool
    {
        return $octets <= $this->resteOctets();
    }

    /**
     * Un nom de fichier sûr, dérivé d'un titre.
     *
     * Le titre vient de Twitch : il peut contenir des barres obliques, des
     * points de suite, n'importe quel caractère d'un alphabet quelconque. Il
     * ne touche jamais le système de fichiers tel quel — un titre comme
     * « ../../.env » n'est pas une hypothèse théorique quand les titres sont
     * saisis par des inconnus.
     */
    public static function nomDeFichier(string $titre, string $extension = 'mp4'): string
    {
        $base = Str::slug(mb_substr($titre, 0, 60)) ?: 'clip';

        return $base.'-'.Str::lower(Str::random(6)).'.'.$extension;
    }
}
