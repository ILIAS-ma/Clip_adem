<?php

namespace App\Models;

use App\Enums\BudgetTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du grand livre. Append-only : jamais d'update, jamais de delete.
 * Une erreur se corrige par une ligne inverse de type Reversal ou Adjustment.
 */
#[Fillable([
    'campaign_id', 'clip_id', 'user_id', 'type', 'amount_cents', 'views_delta',
    'rate_per_1k_cents', 'balance_after_cents', 'idempotency_key', 'created_by', 'meta',
])]
class BudgetTransaction extends Model
{
    use HasFactory;

    protected $table = 'campaign_budget_transactions';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => BudgetTransactionType::class,
            'amount_cents' => 'integer',
            'views_delta' => 'integer',
            'rate_per_1k_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function clip(): BelongsTo
    {
        return $this->belongsTo(Clip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Clé d'idempotence standard pour un crédit issu d'un snapshot de vues. */
    public static function snapshotKey(int $clipId, int $snapshotId): string
    {
        return "clip:{$clipId}:snapshot:{$snapshotId}";
    }
}
