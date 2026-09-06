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
    'creator_id', 'title', 'slug', 'brief', 'required_hashtags',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(CampaignRate::class);
    }

    /** Encaissements reçus du créateur pour financer cette campagne. */
    public function fundings(): HasMany
    {
        return $this->hasMany(CampaignFunding::class)->orderByDesc('received_at');
    }

    /** Pièces du brief : sons, vidéos, images, documents. */
    public function assets(): HasMany
    {
        return $this->hasMany(CampaignAsset::class)->orderBy('position')->orderBy('id');
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

    /** La fenêtre de diffusion a-t-elle commencé ? */
    public function hasStarted(): bool
    {
        return $this->starts_at === null || now()->gte($this->starts_at);
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

        /*
         * Le budget doit être couvert par de l'argent réellement reçu.
         *
         * Sans ce contrôle, `budget_total_cents` n'est qu'un nombre tapé au
         * clavier : la plateforme peut promettre 5 000 € à des clippeurs sans
         * avoir encaissé un centime, et ce sont eux qui ne seraient pas payés.
         *
         * Suspendable comme les autres passages obligés, pour parcourir le
         * back-office sans saisir d'encaissement — mais à rétablir avant
         * l'ouverture, sous peine de devoir de l'argent qu'on n'a pas.
         */
        if (config('clipping.onboarding.require_funded_campaigns') && $this->fundedCents() < $this->budget_total_cents) {
            throw InvalidCampaignTransition::because(sprintf(
                'le budget n’est pas couvert : %s € encaissés pour %s € engagés. '
                .'Enregistrez l’encaissement du créateur avant d’activer.',
                number_format($this->fundedCents() / 100, 2, ',', ' '),
                number_format($this->budget_total_cents / 100, 2, ',', ' '),
            ));
        }
    }

    // ------------------------------------------------------------------
    // Financement
    // ------------------------------------------------------------------

    /** Somme réellement encaissée, remboursements déduits. */
    public function fundedCents(): int
    {
        return (int) $this->fundings()->sum('amount_cents');
    }

    /** Ce qui reste à encaisser pour couvrir le budget engagé. */
    public function unfundedCents(): int
    {
        return max(0, $this->budget_total_cents - $this->fundedCents());
    }

    public function isFullyFunded(): bool
    {
        return $this->unfundedCents() === 0;
    }

    /**
     * L'argent promis aux clippeurs mais pas encore reçu du créateur.
     *
     * C'est le seul chiffre qui dit si la plateforme est solvable sur cette
     * campagne : ce qui a été dépensé au-delà de ce qui a été encaissé sortira
     * de la poche de la plateforme.
     */
    public function exposureCents(): int
    {
        return max(0, $this->spent_cents - $this->fundedCents());
    }
}
