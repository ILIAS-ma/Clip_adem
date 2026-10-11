<?php

namespace App\Models;

use App\Services\Media\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un fichier appartenant à un clippeur : source téléchargée ou montage.
 *
 * Le statut vit sur la ligne et non sur le disque : un montage en cours de
 * rendu n'existe pas encore comme fichier, et un rendu échoué doit pouvoir
 * dire pourquoi. Déduire l'état de la présence du fichier confondrait
 * « en cours » et « échoué », qui demandent deux réponses opposées.
 */
#[Fillable([
    'user_id', 'kind', 'source_id', 'clip_bookmark_id', 'label',
    'path', 'bytes', 'duration_seconds', 'status', 'error', 'options', 'expires_at',
])]
class MediaAsset extends Model
{
    public const SOURCE = 'source';

    public const MONTAGE = 'montage';

    public const EN_ATTENTE = 'en_attente';

    public const EN_COURS = 'en_cours';

    public const PRET = 'pret';

    public const ECHEC = 'echec';

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'duration_seconds' => 'integer',
            'options' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_id');
    }

    public function bookmark(): BelongsTo
    {
        return $this->belongsTo(ClipBookmark::class, 'clip_bookmark_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    public function scopeSources(Builder $query): Builder
    {
        return $query->where('kind', self::SOURCE);
    }

    public function scopeMontages(Builder $query): Builder
    {
        return $query->where('kind', self::MONTAGE);
    }

    public function estPret(): bool
    {
        return $this->status === self::PRET;
    }

    public function enTravail(): bool
    {
        return in_array($this->status, [self::EN_ATTENTE, self::EN_COURS], true);
    }

    public function aEchoue(): bool
    {
        return $this->status === self::ECHEC;
    }

    public function libelleStatut(): string
    {
        return match ($this->status) {
            self::EN_ATTENTE => 'En attente',
            self::EN_COURS => 'En cours',
            self::PRET => 'Prêt',
            self::ECHEC => 'Échec',
            default => $this->status,
        };
    }

    /** Taille lisible : « 18,4 Mo » plutôt que 19 293 184. */
    public function tailleLisible(): string
    {
        if ($this->bytes < 1024 * 1024) {
            return max(1, intdiv($this->bytes, 1024)).' Ko';
        }

        return number_format($this->bytes / 1024 / 1024, 1, ',', ' ').' Mo';
    }

    public function dureeLisible(): string
    {
        $s = (int) $this->duration_seconds;

        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    /** Chemin absolu du fichier, pour ffmpeg. */
    public function cheminAbsolu(): ?string
    {
        return $this->path ? storage_path('app/private/'.$this->path) : null;
    }

    /**
     * Efface le fichier puis la ligne.
     *
     * Dans cet ordre : une ligne supprimée alors que le fichier reste laisse
     * un octet qui ne compte plus dans le quota et que plus rien ne peut
     * retrouver. L'inverse — fichier parti, ligne restante — se répare.
     */
    public function supprimerAvecFichier(): void
    {
        if ($this->path) {
            Storage::disk(Workspace::DISQUE)->delete($this->path);
        }

        $this->delete();
    }
}
