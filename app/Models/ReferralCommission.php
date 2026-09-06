<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commission de parrainage, en ajout seul.
 *
 * L'argent vient de la marge de la plateforme, jamais du budget d'une
 * campagne : le créateur n'a pas à financer la croissance de la plateforme.
 * Une reprise est une ligne négative, pas une suppression.
 */
#[Fillable([
    'referrer_id', 'referred_id', 'budget_transaction_id',
    'amount_cents', 'base_cents', 'rate_bp',
])]
class ReferralCommission extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'base_cents' => 'integer',
            'rate_bp' => 'integer',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function budgetTransaction(): BelongsTo
    {
        return $this->belongsTo(BudgetTransaction::class);
    }

    /** Le barème appliqué, lisible. */
    public function ratePercent(): float
    {
        return $this->rate_bp / 100;
    }
}
