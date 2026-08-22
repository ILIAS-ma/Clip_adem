<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Exhausted = 'exhausted';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Active => 'Active',
            self::Paused => 'En pause',
            self::Exhausted => 'Épuisée',
            self::Completed => 'Terminée',
            self::Archived => 'Archivée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Paused => 'warning',
            self::Exhausted => 'danger',
            self::Completed => 'info',
            self::Archived => 'gray',
        };
    }

    /**
     * Seule une campagne active consomme du budget.
     * Toute autre valeur fait sortir creditViews() en « skipped ».
     */
    public function acceptsCredits(): bool
    {
        return $this === self::Active;
    }

    /** Une campagne épuisée n'accepte plus de clips mais reste consultable. */
    public function acceptsNewClips(): bool
    {
        return $this === self::Active;
    }

    /** @return array<int, self> Transitions autorisées depuis ce statut. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Archived],
            self::Active => [self::Paused, self::Exhausted, self::Completed],
            self::Paused => [self::Active, self::Completed],
            // Retour possible en Active uniquement si le budget total est augmenté.
            self::Exhausted => [self::Active, self::Completed],
            self::Completed => [self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
