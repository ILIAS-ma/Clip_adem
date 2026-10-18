<?php

namespace App\Enums;

use App\Support\Money;

/** Ce sur quoi se joue un classement. */
enum ContestMetric: string
{
    /** Les vues cumulées des clips validés. */
    case Views = 'views';

    /** Le nombre de clips validés : la régularité plutôt que le viral. */
    case Posts = 'posts';

    public function label(): string
    {
        return match ($this) {
            self::Views => 'Vues',
            self::Posts => 'Nombre de clips postés',
        };
    }

    /** « classés sur … », pour les phrases destinées aux clippeurs. */
    public function rankedOn(): string
    {
        return match ($this) {
            self::Views => 'les vues de leurs clips validés',
            self::Posts => 'le nombre de clips validés',
        };
    }

    /** Titre court du tableau : « Top vues », « Top clips ». */
    public function title(): string
    {
        return match ($this) {
            self::Views => 'Top vues',
            self::Posts => 'Top clips',
        };
    }

    /** Pour les phrases : « 1re place du classement des vues ». */
    public function boardName(): string
    {
        return match ($this) {
            self::Views => 'classement des vues',
            self::Posts => 'classement des clips',
        };
    }

    /** Le chiffre qui classe, prêt à afficher : « 12 clips » ou « 878k vues ». */
    public function formatScore(int $score): string
    {
        return match ($this) {
            self::Views => Money::compactViews($score).' vues',
            self::Posts => $score.' clip'.($score > 1 ? 's' : ''),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $metric) => [$metric->value => $metric->label()])->all();
    }
}
