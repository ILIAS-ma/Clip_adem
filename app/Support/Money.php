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

    /** Vues en format court, à la manière des plateformes : « 878k », « 1,2M ». */
    public static function compactViews(?int $views): string
    {
        $views = (int) $views;

        return match (true) {
            $views >= 1_000_000 => rtrim(rtrim(number_format($views / 1_000_000, 1, ',', ''), '0'), ',').'M',
            $views >= 1_000 => intdiv($views, 1_000).'k',
            default => (string) $views,
        };
    }

    /** Montant arrondi à l'euro, pour les compteurs : « 12 480 € ». */
    public static function wholeEuros(int $cents): string
    {
        return number_format(intdiv($cents, 100), 0, ',', ' ').' €';
    }
}
