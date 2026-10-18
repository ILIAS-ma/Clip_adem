<?php

namespace App\Models;

use App\Enums\BudgetTransactionType;
use App\Enums\CampaignMode;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ContestMetric;
use App\Enums\Platform;
use App\Exceptions\InvalidCampaignTransition;
use App\Support\Campaigns\Reward;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'creator_id', 'category_id', 'mode', 'contest_prizes', 'contest_post_prizes', 'contest_post_min_views', 'title', 'cover_path', 'slug', 'brief', 'required_hashtags',
    'status', 'currency', 'budget_total_cents', 'min_views_per_clip',
    'min_clips_per_clipper', 'max_payout_per_clip_cents',
    'launch_bonus_cents', 'launch_bonus_slots',
    'starts_at', 'ends_at', 'requires_approval', 'created_by',
])]
class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    /** Le mode par défaut, aussi en mémoire avant le premier rechargement. */
    protected $attributes = [
        'mode' => 'cpm',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'mode' => CampaignMode::class,
            'contest_prizes' => 'array',
            'contest_post_prizes' => 'array',
            'contest_post_min_views' => 'integer',
            'prizes_paid_at' => 'datetime',
            'contest_winners' => 'array',
            'required_hashtags' => 'array',
            'budget_total_cents' => 'integer',
            'spent_cents' => 'integer',
            'min_views_per_clip' => 'integer',
            'min_clips_per_clipper' => 'integer',
            'max_payout_per_clip_cents' => 'integer',
            'launch_bonus_cents' => 'integer',
            'launch_bonus_slots' => 'integer',
            'requires_approval' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'exhausted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** Image de couverture, ou null si la campagne n'en a pas. */
    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    // ------------------------------------------------------------------
    // Mode classement
    // ------------------------------------------------------------------

    public function isContest(): bool
    {
        return $this->mode === CampaignMode::Contest;
    }

    /**
     * Récompenses d'un des deux classements, par place, dans l'ordre :
     * index 0 = 1re place. Argent ou objet ; une liste vide = classement
     * sans récompense.
     *
     * @return list<Reward>
     */
    public function rewards(ContestMetric $board = ContestMetric::Views): array
    {
        $raw = $board === ContestMetric::Posts ? $this->contest_post_prizes : $this->contest_prizes;

        return collect($raw ?? [])
            ->map(fn ($item) => Reward::from($item))
            ->filter()
            ->values()
            ->all();
    }

    /** La récompense d'une place (1 = première), ou null si elle n'en a pas. */
    public function rewardFor(int $rank, ContestMetric $board = ContestMetric::Views): ?Reward
    {
        return $this->rewards($board)[$rank - 1] ?? null;
    }

    /** Toutes les récompenses, des deux classements. @return list<Reward> */
    public function allRewards(): array
    {
        return [...$this->rewards(ContestMetric::Views), ...$this->rewards(ContestMetric::Posts)];
    }

    /**
     * Vues qu'un clip doit atteindre pour compter au classement des clips.
     * Dix clips à quarante vues ne doivent pas battre trois clips qui marchent.
     */
    public function postMinViews(): int
    {
        return max(0, (int) ($this->contest_post_min_views ?? 1000));
    }

    /** L'argent promis sur les deux classements, seule part qui pèse sur le budget. */
    public function prizePoolCents(): int
    {
        return collect($this->allRewards())->filter->isMoney()->sum('amountCents');
    }

    public function hasItemRewards(): bool
    {
        return collect($this->allRewards())->contains->isItem();
    }

    /**
     * Le classement est arrêté : la date de fin est passée, ou la campagne
     * est terminée. C'est le moment où les prix peuvent être versés.
     */
    public function contestIsOver(): bool
    {
        return $this->status === CampaignStatus::Completed
            || ($this->ends_at !== null && now()->gt($this->ends_at));
    }

    /** Places de prime de lancement restantes, ou null sans prime. */
    public function launchBonusSlotsLeft(): ?int
    {
        if (! $this->launch_bonus_cents || ! $this->launch_bonus_slots) {
            return null;
        }

        $granted = $this->budgetTransactions()
            ->where('type', BudgetTransactionType::Bonus)
            ->where('meta->kind', 'launch')
            ->count();

        return max(0, $this->launch_bonus_slots - $granted);
    }

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
        return $this->isRunning() && $this->remainingCents() > 0;
    }

    /**
     * La campagne tourne : active, non retirée, dans sa fenêtre de diffusion.
     * Sans regarder le budget — c'est acceptsCredits() qui ajoute « et il
     * reste de quoi payer ».
     */
    public function isRunning(): bool
    {
        /*
         * Une campagne retirée du catalogue ne paie plus.
         *
         * La suppression est douce — les clips gardent leur histoire — mais
         * elle doit arrêter l'argent : sans ce test, un clip continuerait
         * d'être crédité sur une campagne que plus personne ne voit ni ne
         * surveille.
         */
        if ($this->trashed()) {
            return false;
        }

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

        return true;
    }

    /**
     * Les clippeurs peuvent-ils encore participer ?
     *
     * Une campagne payée aux vues ferme quand son budget est épuisé. Un
     * classement, lui, ne dépense rien pendant qu'il court — il peut même
     * n'avoir aucun budget s'il récompense en objets, ou pas du tout : il
     * reste ouvert tant qu'il tourne.
     */
    public function isOpenForClips(): bool
    {
        return $this->isContest() ? $this->isRunning() : $this->acceptsCredits();
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

    /**
     * Clips validés de ce clippeur sur cette campagne.
     *
     * Une seule définition de « clip qui compte », partagée par le moteur de
     * budget et par l'affichage : si les deux divergeaient, le clippeur
     * verrait « 5 / 5 » sans être payé, ou l'inverse — et dans les deux cas
     * il conclurait à un bug.
     *
     * Validés seulement : cinq soumissions bâclées ne doivent pas suffire à
     * ouvrir le robinet, ce que la règle existe précisément pour empêcher.
     */
    public function payableClipCountFor(int $userId): int
    {
        return (int) $this->clips()
            ->where('user_id', $userId)
            ->whereIn('status', array_filter(
                ClipStatus::cases(),
                fn (ClipStatus $s) => $s->isPayable(),
            ))
            ->count();
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
        /*
         * Un classement sans récompense en argent — objets seulement, ou pour
         * la gloire — n'engage aucun budget : il n'y a rien à exiger ni à
         * faire couvrir. Dès qu'il promet de l'argent, les règles d'argent
         * s'appliquent comme partout.
         */
        $needsBudget = ! $this->isContest() || $this->prizePoolCents() > 0;

        if ($needsBudget && $this->budget_total_cents <= 0) {
            throw InvalidCampaignTransition::because('le budget total doit être supérieur à 0.');
        }

        if ($needsBudget && $this->remainingCents() <= 0) {
            throw InvalidCampaignTransition::because(
                'le budget est déjà consommé : augmentez le budget total avant de réactiver.'
            );
        }

        if ($this->rates()->where('is_enabled', true)->doesntExist()) {
            throw InvalidCampaignTransition::because($this->isContest()
                ? 'au moins une plateforme doit être ouverte.'
                : 'au moins une plateforme doit avoir un taux actif.');
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
        if ($needsBudget && config('clipping.onboarding.require_funded_campaigns') && $this->fundedCents() < $this->budget_total_cents) {
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
