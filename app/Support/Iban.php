<?php

namespace App\Support;

/**
 * Normalisation, vérification et masquage d'un IBAN.
 *
 * La vérification est purement structurelle (longueur par pays + clé mod 97).
 * Elle attrape les fautes de frappe — un chiffre inversé, un caractère en
 * trop — pas les comptes fermés : seule la banque peut dire ça, au moment du
 * virement. C'est déjà l'essentiel, parce qu'un virement rejeté coûte des
 * frais et une semaine.
 */
class Iban
{
    /** Longueur totale attendue, par code pays. Zone SEPA principale. */
    private const LENGTHS = [
        'AD' => 24, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28,
        'CZ' => 24, 'DE' => 22, 'DK' => 18, 'EE' => 20, 'ES' => 24, 'FI' => 18,
        'FR' => 27, 'GB' => 22, 'GI' => 23, 'GR' => 27, 'HR' => 21, 'HU' => 28,
        'IE' => 22, 'IS' => 26, 'IT' => 27, 'LI' => 21, 'LT' => 20, 'LU' => 20,
        'LV' => 21, 'MC' => 27, 'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28,
        'PT' => 25, 'RO' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27,
    ];

    /** Retire espaces et tirets, passe en majuscules. */
    public static function normalize(?string $iban): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $iban) ?? '');
    }

    public static function isValid(?string $iban): bool
    {
        $iban = self::normalize($iban);

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }

        $country = substr($iban, 0, 2);

        if (! isset(self::LENGTHS[$country]) || strlen($iban) !== self::LENGTHS[$country]) {
            return false;
        }

        return self::mod97($iban) === 1;
    }

    /** Clé de contrôle ISO 7064 : les quatre premiers caractères passent à la fin. */
    private static function mod97(string $iban): int
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $digits = '';
        foreach (str_split($rearranged) as $char) {
            // A → 10, B → 11, … Z → 35.
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // bcmod éviterait la boucle, mais l'extension n'est pas garantie.
        $remainder = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ((string) $remainder.$chunk) % 97;
        }

        return $remainder;
    }

    public static function last4(?string $iban): ?string
    {
        $iban = self::normalize($iban);

        return strlen($iban) >= 4 ? substr($iban, -4) : null;
    }

    /** Affichage par groupes de quatre : FR76 3000 4000 … */
    public static function format(?string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }

    /** Ce qu'on montre à l'écran et ce qu'on écrit dans l'historique. */
    public static function mask(?string $iban): string
    {
        $last4 = self::last4($iban);

        return $last4 === null ? '' : '•••• •••• '.$last4;
    }
}
