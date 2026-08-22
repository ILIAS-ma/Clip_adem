<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'slug', 'bio', 'avatar_path', 'spotify_url',
    'instagram_handle', 'tiktok_handle', 'youtube_handle',
    'internal_notes', 'is_active', 'created_by',
])]
class Artist extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Budget total engagé sur l'artiste, toutes campagnes confondues. */
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
