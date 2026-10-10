<?php

namespace App\Support;

/**
 * Normalisation d'un numéro de téléphone.
 *
 * « 06 12 34 56 78 », « 0612345678 » et « +33 6 12 34 56 78 » désignent le
 * même abonné. Les stocker tels quels permettrait à trois comptes de réserver
 * la même ligne — et la contrainte d'unicité, qui est précisément le
 * garde-fou contre les comptes multiples, ne verrait rien.
 */
class PhoneNumber
{
    /** Indicatifs des pays servis, dans l'ordre du formulaire d'inscription. */
    public const INDICATIFS = [
        'FR' => '33', 'BE' => '32', 'CH' => '41', 'LU' => '352',
        'CA' => '1', 'MA' => '212', 'DZ' => '213', 'TN' => '216',
        'SN' => '221', 'CI' => '225',
    ];

    /**
     * Rend le numéro au format international, ou null s'il est inexploitable.
     *
     * Le pays sert uniquement à interpréter un numéro national ; un numéro
     * déjà préfixé de « + » est pris tel quel, car l'abonné sait mieux que
     * nous d'où il appelle.
     */
    public static function normalise(?string $brut, ?string $pays = 'FR'): ?string
    {
        if ($brut === null) {
            return null;
        }

        $chiffres = preg_replace('/[^\d+]/', '', $brut) ?? '';

        if ($chiffres === '') {
            return null;
        }

        if (str_starts_with($chiffres, '+')) {
            $national = ltrim(substr($chiffres, 1), '0');

            return $national === '' ? null : '+'.$national;
        }

        // « 00 » est l'autre façon d'écrire « + », encore courante en Europe.
        if (str_starts_with($chiffres, '00')) {
            $national = ltrim(substr($chiffres, 2), '0');

            return $national === '' ? null : '+'.$national;
        }

        $indicatif = self::INDICATIFS[strtoupper((string) $pays)] ?? null;

        if ($indicatif === null) {
            return null;
        }

        // Le zéro de tête est une convention nationale : il disparaît dès
        // qu'on passe à l'international.
        $national = ltrim($chiffres, '0');

        return $national === '' ? null : '+'.$indicatif.$national;
    }

    /** Masque le numéro pour l'affichage : +33 6 •• •• •• 78 */
    public static function masque(?string $normalise): string
    {
        if ($normalise === null || strlen($normalise) < 6) {
            return '—';
        }

        return substr($normalise, 0, 4).str_repeat('•', max(0, strlen($normalise) - 6)).substr($normalise, -2);
    }
}
