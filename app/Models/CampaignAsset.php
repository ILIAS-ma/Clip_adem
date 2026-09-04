<?php

namespace App\Models;

use App\Enums\AssetKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Une pièce du brief : le son imposé, un exemple à regarder, un logo à
 * incruster, une charte à respecter.
 *
 * Fichier déposé OU lien externe, jamais les deux : ce qui est déjà hébergé
 * ailleurs (un son TikTok, un Drive partagé) n'a pas à être recopié, et ce
 * qu'on héberge doit rester servi par nous pour ne pas casser au premier lien
 * expiré.
 */
#[Fillable([
    'campaign_id', 'kind', 'label', 'description',
    'path', 'external_url', 'mime_type', 'size_bytes', 'is_required', 'position',
])]
class CampaignAsset extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => AssetKind::class,
            'is_required' => 'boolean',
            'size_bytes' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CampaignAsset $asset) {
            if (! $asset->path) {
                return;
            }

            // Un fichier déposé l'emporte sur un lien : garder les deux
            // laisserait deux vérités sur ce qu'il faut réellement utiliser.
            $asset->external_url = null;

            // Poids et type relus depuis le disque plutôt que fournis par le
            // formulaire : le navigateur peut se tromper, le disque non.
            if ($asset->isDirty('path') && Storage::disk('public')->exists($asset->path)) {
                $asset->size_bytes = Storage::disk('public')->size($asset->path);
                $asset->mime_type = Storage::disk('public')->mimeType($asset->path) ?: null;
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** Adresse à ouvrir, quelle que soit l'origine de la pièce. */
    public function url(): ?string
    {
        if ($this->path) {
            return Storage::disk('public')->url($this->path);
        }

        return $this->external_url ?: null;
    }

    public function isHosted(): bool
    {
        return (bool) $this->path;
    }

    /** Poids lisible, pour prévenir avant un téléchargement de 180 Mo. */
    public function humanSize(): ?string
    {
        if (! $this->size_bytes) {
            return null;
        }

        $units = ['o', 'ko', 'Mo', 'Go'];
        $size = (float) $this->size_bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1, ',', ' ').' '.$units[$unit];
    }

    /** L'aperçu n'a de sens que pour ce que le navigateur sait lire. */
    public function isPreviewable(): bool
    {
        return $this->isHosted()
            && in_array($this->kind, [AssetKind::Audio, AssetKind::Video, AssetKind::Image], true);
    }
}
