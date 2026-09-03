<?php

namespace App\Support\Clippers;

use App\Enums\ClipperLevel;

/**
 * Photographie de la progression d'un clippeur à un instant donné.
 *
 * Rien n'est stocké : tout se recalcule depuis les clips et le grand livre. Un
 * compteur dénormalisé finirait par diverger, et il donnerait alors des
 * avantages à quelqu'un qui ne les a pas gagnés.
 */
final readonly class Progression
{
    public function __construct(
        public ClipperLevel $level,
        public int $careerXp,

        /** Vues rémunérées sur la fenêtre d'activité. */
        public int $recentViews,

        /** Les avantages du niveau sont-ils actifs ? */
        public bool $perksActive,

        public int $paidViews,
        public int $approvedClips,
        public int $invalidatedClips,
        public int $campaignsCount,
    ) {}

    public function nextLevel(): ?ClipperLevel
    {
        return $this->level->next();
    }

    /** Expérience restante avant le niveau suivant. */
    public function xpToNextLevel(): ?int
    {
        return $this->nextLevel()
            ? max(0, $this->nextLevel()->threshold() - $this->careerXp)
            : null;
    }

    /** Avancement vers le niveau suivant, en pourcentage. */
    public function progressPercent(): float
    {
        $next = $this->nextLevel();

        if (! $next) {
            return 100.0;
        }

        $span = $next->threshold() - $this->level->threshold();

        if ($span <= 0) {
            return 100.0;
        }

        return round(min(100, ($this->careerXp - $this->level->threshold()) / $span * 100), 1);
    }

    /** Ce qu'il manque pour réactiver les avantages, en vues. */
    public function viewsToReactivate(): int
    {
        return max(0, $this->level->activityFloor() - $this->recentViews);
    }

    public function earlyAccessHours(): int
    {
        return $this->perksActive ? $this->level->earlyAccessHours() : 0;
    }

    public function clipCapMultiplier(): float
    {
        return $this->perksActive ? $this->level->clipCapMultiplier() : 1.0;
    }
}
