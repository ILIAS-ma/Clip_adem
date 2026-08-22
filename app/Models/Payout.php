<?php

namespace App\Models;

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
    'user_id', 'amount_cents', 'currency', 'status', 'paypal_email',
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
}
