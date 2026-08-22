<?php

namespace App\Models;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ATTENTION — frontière d'écriture.
 *
 * Le module clippeur écrit views_total, last_synced_at et le statut de
 * publication. Les colonnes paid_views et earned_cents ne sont écrites que par
 * CampaignBudgetService, à l'intérieur d'une transaction verrouillée : elles ne
 * figurent volontairement pas dans #[Fillable].
 */
#[Fillable([
    'campaign_id', 'participation_id', 'user_id', 'social_account_id',
    'platform', 'external_post_id', 'url', 'posted_at', 'status',
    'rejection_reason', 'views_total', 'last_synced_at',
])]
class Clip extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'status' => ClipStatus::class,
            'views_total' => 'integer',
            'paid_views' => 'integer',
            'earned_cents' => 'integer',
            'posted_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(CampaignParticipation::class, 'participation_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ClipViewSnapshot::class);
    }

    public function budgetTransactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class);
    }

    /**
     * Vues comptabilisées mais non rémunérées : soit la campagne est épuisée,
     * soit un plafond anti-abus a été atteint. À afficher au clippeur pour
     * qu'il comprenne pourquoi ses gains ont cessé de monter.
     */
    public function unpaidViews(): int
    {
        return max(0, $this->views_total - $this->paid_views);
    }
}
