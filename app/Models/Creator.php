<?php

namespace App\Models;

use App\Enums\CreatorKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'name', 'slug', 'kind', 'bio', 'avatar_path', 'spotify_url',
    'instagram_handle', 'tiktok_handle', 'youtube_handle',
    'twitch_handle', 'kick_handle', 'vod_url',
    'internal_notes', 'is_active', 'created_by',
])]
class Creator extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * L'identifiant n'apparaît dans aucune adresse du site : c'est une clé
     * interne. Il se fabrique donc tout seul à la création, depuis le nom,
     * plutôt que d'être demandé dans chaque formulaire.
     */
    protected static function booted(): void
    {
        static::creating(function (Creator $creator) {
            if (blank($creator->slug)) {
                $creator->slug = static::uniqueSlug((string) $creator->name);
            }
        });
    }

    /** « Saya », puis « saya-2 », « saya-3 »… fiches supprimées comprises. */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'createur';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'kind' => CreatorKind::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nature de la fiche, jamais nulle.
     *
     * Une fiche créée avant l'arrivée des streamers, ou par un code qui
     * ignore la colonne, vaut artiste. Sans ce filet, l'espace créateur
     * appellerait `->label()` sur `null` et tomberait sur une fiche
     * parfaitement valable.
     */
    public function kind(): CreatorKind
    {
        return $this->kind ?? CreatorKind::Artist;
    }

    public function isStreamer(): bool
    {
        return $this->kind() === CreatorKind::Streamer;
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Compte de connexion de le créateur, s'il en a un. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Vues cumulées sur toutes ses campagnes. */
    public function totalViews(): int
    {
        return (int) Clip::whereIn('campaign_id', $this->campaigns()->select('id'))->sum('views_total');
    }

    /** Budget total engagé sur le créateur, toutes campagnes confondues. */
    public function budgetTotalCents(): int
    {
        return (int) $this->campaigns()->sum('budget_total_cents');
    }

    public function spentCents(): int
    {
        return (int) $this->campaigns()->sum('spent_cents');
    }

    /**
     * Coût réel pour 1000 vues, en centimes. C'est le seul indicateur de ROI
     * qui ait du sens ici : à comparer au CPM affiché sur les campagnes.
     */
    public function realCostPer1kCents(): ?int
    {
        $views = (int) Clip::whereIn('campaign_id', $this->campaigns()->select('id'))->sum('views_total');

        if ($views === 0) {
            return null;
        }

        return intdiv($this->spentCents() * 1000, $views);
    }
}
