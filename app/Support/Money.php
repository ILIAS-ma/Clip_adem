<?php

namespace App\Support;

/**
 * Formatage des montants pour l'affichage.
 *
 * Un seul endroit fait la conversion centimes → euros dans les vues : partout
 * ailleurs, l'argent reste un entier de centimes.
 */
final class Money
{
    public static function euros(?int $cents, string $placeholder = '—'): string
    {
        if ($cents === null) {
            return $placeholder;
        }

        return number_format($cents / 100, 2, ',', ' ').' €';
    }

    /** Cachet CPM, arrondi à l'unité quand il tombe juste : « 0,50 € » mais « 2 € ». */
    public static function rate(int $cents): string
    {
        $decimals = $cents % 100 === 0 ? 0 : 2;

        return number_format($cents / 100, $decimals, ',', ' ').' €';
    }

    public static function views(?int $views): string
    {
        return number_format((int) $views, 0, ',', ' ');
    }
}
