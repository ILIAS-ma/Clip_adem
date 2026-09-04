<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versement PayPal vers un clippeur.
 *
 * Un payout ne touche JAMAIS le budget d'une campagne : le budget est consommé
 * au moment où les vues sont créditées, pas au moment du paiement. Un payout
 * échoué rend le solde au clippeur, pas au budget.
 */
#[Fillable([
    'user_id', 'amount_cents', 'currency', 'status', 'method', 'destination', 'paypal_email',
    'paypal_batch_id', 'paypal_payout_item_id',
    'requested_at', 'approved_at', 'processed_at', 'failure_reason', 'approved_by',
])]
class Payout extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'method' => PayoutMethod::class,
            'amount_cents' => 'integer',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** PayPal par défaut : les retraits antérieurs au RIB n'ont pas de mode. */
    public function payoutMethod(): PayoutMethod
    {
        return $this->method ?? PayoutMethod::PayPal;
    }

    /**
     * Où l'argent est parti, figé au moment de la demande.
     *
     * On ne relit jamais le profil : un changement de RIB ne doit pas réécrire
     * l'histoire d'un virement déjà exécuté.
     */
    public function destinationLabel(): string
    {
        return $this->destination ?: ($this->paypal_email ?: '—');
    }

    /** Un virement bancaire est exécuté à la main, puis pointé ici. */
    public function isManual(): bool
    {
        return ! $this->payoutMethod()->isAutomatic();
    }
}
