<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro SIRET : 14 chiffres et clé de Luhn.
 *
 * La clé attrape les fautes de frappe avant qu'un administrateur ne cherche au
 * registre un établissement qui n'existe pas. Elle ne prouve pas que le numéro
 * appartient à la personne : c'est le rôle de la vérification humaine.
 */
class Siret implements ValidationRule
{
    /** SIREN de La Poste : ses établissements suivent une règle propre. */
    protected const LA_POSTE_SIREN = '356000000';

    public static function normalize(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    public static function isValid(?string $value): bool
    {
        $siret = static::normalize($value);

        if (strlen($siret) !== 14) {
            return false;
        }

        if (str_starts_with($siret, self::LA_POSTE_SIREN)) {
            return array_sum(str_split($siret)) % 5 === 0;
        }

        $sum = 0;

        foreach (str_split($siret) as $index => $digit) {
            $value = (int) $digit;

            // En partant de la gauche sur 14 chiffres, ce sont les positions
            // paires (index 0, 2…) qui sont doublées.
            if ($index % 2 === 0) {
                $value *= 2;
                $value = $value > 9 ? $value - 9 : $value;
            }

            $sum += $value;
        }

        return $sum % 10 === 0;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isValid(is_string($value) ? $value : null)) {
            $fail('Ce numéro SIRET n’est pas valide : il compte 14 chiffres, vérifiez-le sur votre avis de situation.');
        }
    }
}
