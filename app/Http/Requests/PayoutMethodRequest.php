<?php

namespace App\Http\Requests;

use App\Enums\PayoutMethod;
use App\Rules\ValidIban;
use App\Support\Iban;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Choix du moyen de paiement, partagé par la fin d'inscription et la page
 * « Moyen de paiement ».
 *
 * Un seul endroit valide ces champs : deux formulaires avec deux jeux de règles
 * finiraient par diverger, et c'est justement le chemin par lequel l'argent
 * part.
 */
class PayoutMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $method = $this->input('payout_method');

        return [
            'payout_method' => ['required', Rule::enum(PayoutMethod::class)],

            // `required_if` plutôt que `required` : on ne réclame que les
            // champs du mode réellement choisi.
            'paypal_email' => [
                Rule::requiredIf($method === PayoutMethod::PayPal->value),
                'nullable', 'email', 'max:255',
            ],

            'account_holder' => [
                Rule::requiredIf($method === PayoutMethod::BankTransfer->value),
                'nullable', 'string', 'max:120',
            ],
            'iban' => [
                Rule::requiredIf($method === PayoutMethod::BankTransfer->value),
                'nullable', 'string', 'max:42', new ValidIban,
            ],
            // Le BIC est facultatif en SEPA depuis 2016 ; certaines banques
            // hors zone euro le réclament encore.
            'bic' => ['nullable', 'string', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'paypal_email.required' => 'Indiquez l’adresse PayPal qui recevra vos retraits.',
            'account_holder.required' => 'Indiquez le nom du titulaire du compte, tel qu’il figure sur le RIB.',
            'iban.required' => 'Indiquez l’IBAN qui recevra vos virements.',
            'bic.regex' => 'Ce BIC est invalide : 8 ou 11 caractères, comme BNPAFRPP.',
        ];
    }

    /**
     * Les colonnes à écrire sur l'utilisateur.
     *
     * Les champs du mode non retenu sont effacés : garder un IBAN dormant sur
     * un compte passé à PayPal, c'est conserver une donnée bancaire dont plus
     * personne n'a l'usage.
     *
     * @return array<string, mixed>
     */
    public function payoutAttributes(): array
    {
        $method = PayoutMethod::from($this->validated('payout_method'));

        if ($method === PayoutMethod::PayPal) {
            return [
                'payout_method' => $method,
                'paypal_email' => $this->validated('paypal_email'),
                'iban' => null,
                'iban_last4' => null,
                'bic' => null,
                'account_holder' => null,
            ];
        }

        $iban = Iban::normalize($this->validated('iban'));

        return [
            'payout_method' => $method,
            'paypal_email' => null,
            'iban' => $iban,
            'iban_last4' => Iban::last4($iban),
            'bic' => strtoupper((string) $this->validated('bic')) ?: null,
            'account_holder' => $this->validated('account_holder'),
        ];
    }
}
