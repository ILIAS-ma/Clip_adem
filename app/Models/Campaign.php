<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Exceptions\InvalidCampaignTransition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'artist_id', 'title', 'slug', 'brief', 'required_hashtags', 'audio_url', 'assets_url',
    'status', 'currency', 'budget_total_cents', 'target_views', 'min_views_per_clip',
    'max_payout_per_clip_cents', 'max_payout_per_clipper_cents',
    'starts_at', 'ends_at', 'requires_approval', 'created_by',
])]
class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'required_hashtags' => 'array',
            'budget_total_cents' => 'integer',
            'spent_cents' => 'integer',
            'target_views' => 'integer',
            'min_views_per_clip' => 'integer',
            'max_payout_per_clip_cents' => 'integer',
            'max_payout_per_clipper_cents' => 'integer',
            'requires_approval' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'exhausted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(CampaignRate::class);
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(CampaignParticipation::class);
    }

    public function budgetTransactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class);
    }

    // ------------------------------------------------------------------
    // Budget — lecture seule. Toute écriture passe par CampaignBudgetService.
    // ------------------------------------------------------------------

    public function remainingCents(): int
    {
        return max(0, $this->budget_total_cents - $this->spent_cents);
    }

    public function consumedPercent(): float
    {
        if ($this->budget_total_cents === 0) {
            return 0.0;
        }

        return round($this->spent_cents / $this->budget_total_cents * 100, 2);
    }

    /** Taux CPM applicable à une plateforme, ou null si elle n'est pas ouverte. */
    public function rateFor(Platform $platform): ?int
    {
        $rate = $this->relationLoaded('rates')
            ? $this->rates->firstWhere(fn (CampaignRate $rate) => $rate->platform === $platform && $rate->is_enabled)
            : $this->rates()->where('platform', $platform->value)->where('is_enabled', true)->first();

        return $rate?->rate_per_1k_cents;
    }

    /**
     * Une campagne ne consomme du budget que si elle est active ET dans sa
     * fenêtre de diffusion. Les dates sont vérifiées ici, pas seulement à
     * l'affichage : sans ça une campagne terminée continuerait de payer.
     */
    public function acceptsCredits(): bool
    {
        if (! $this->status->acceptsCredits()) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return $this->remainingCents() > 0;
    }

    public function isExhausted(): bool
    {
        return $this->spent_cents >= $this->budget_total_cents;
    }

    /**
     * Visible dans l'espace clippeur.
     *
     * Une campagne épuisée reste consultable — grisée et non rejoignable — pour
     * que les clippeurs qui y ont des clips puissent continuer d'en suivre les
     * vues. Un brouillon ou une archive, non.
     */
    public function isVisibleToClippers(): bool
    {
        return in_array($this->status, [
            CampaignStatus::Active,
            CampaignStatus::Paused,
            CampaignStatus::Exhausted,
            CampaignStatus::Completed,
        ], true);
    }

    /** @param  Builder<self>  $query */
    public function scopeVisibleToClippers($query)
    {
        return $query->whereIn('status', [
            CampaignStatus::Active,
            CampaignStatus::Paused,
            CampaignStatus::Exhausted,
            CampaignStatus::Completed,
        ]);
    }

    // ------------------------------------------------------------------
    // Machine à états
    // ------------------------------------------------------------------

    /**
     * @throws InvalidCampaignTransition
     */
    public function transitionTo(CampaignStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw InvalidCampaignTransition::between($this->status, $target);
        }

        if ($target === CampaignStatus::Active) {
            $this->guardActivation();
        }

        $this->status = $target;

        if ($target === CampaignStatus::Exhausted) {
            $this->exhausted_at = now();
        }

        if ($target === CampaignStatus::Completed) {
            $this->completed_at = now();
        }

        if ($target === CampaignStatus::Active) {
            $this->exhausted_at = null;
        }

        $this->save();
    }

    /**
     * Activer une campagne sans budget, sans taux ou sans brief produit des
     * clips que personne ne peut payer. On bloque en amont.
     *
     * @throws InvalidCampaignTransition
     */
    protected function guardActivation(): void
    {
        if ($this->budget_total_cents <= 0) {
            throw InvalidCampaignTransition::because('le budget total doit être supérieur à 0.');
        }

        if ($this->remainingCents() <= 0) {
            throw InvalidCampaignTransition::because(
                'le budget est déjà consommé : augmentez le budget total avant de réactiver.'
            );
        }

        if ($this->rates()->where('is_enabled', true)->doesntExist()) {
            throw InvalidCampaignTransition::because('au moins une plateforme doit avoir un taux actif.');
        }

        if (blank($this->brief)) {
            throw InvalidCampaignTransition::because('le brief est obligatoire pour activer une campagne.');
        }
    }
}
