<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Fin d'inscription : identité publique + moyen de paiement, en une passe.
 *
 * Hérite des règles de paiement au lieu de les recopier : la fin d'inscription
 * et la page « Moyen de paiement » doivent accepter exactement les mêmes IBAN.
 */
class CompleteProfileRequest extends PayoutMethodRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'pseudo' => [
                'required', 'string', 'min:3', 'max:48',
                // Le pseudo apparaît dans les classements publics : on le veut
                // lisible et sans usurpation d'identité par caractères exotiques.
                'regex:/^[\pL\pN._\- ]+$/u',
                Rule::unique('users', 'pseudo')->ignore($this->user()->id),
            ],
            'country' => ['required', 'string', 'size:2'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'pseudo.regex' => 'Le pseudo ne peut contenir que des lettres, chiffres, espaces, points, tirets et underscores.',
        ];
    }

    /** @return array<string, mixed> */
    public function profileAttributes(): array
    {
        return [
            ...$this->payoutAttributes(),
            'pseudo' => $this->validated('pseudo'),
            'country' => strtoupper($this->validated('country')),
            'profile_completed_at' => now(),
        ];
    }
}
