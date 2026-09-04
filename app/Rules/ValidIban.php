<?php

namespace App\Rules;

use App\Support\Iban;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Iban::isValid(is_string($value) ? $value : null)) {
            $fail('Cet IBAN est invalide. Recopiez-le depuis votre RIB, espaces compris.');
        }
    }
}
