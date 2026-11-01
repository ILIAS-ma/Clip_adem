<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** La demande d'un clippeur qui attend les prix d'un classement terminé. */
#[Fillable(['user_id', 'campaign_id', 'claimed_at'])]
class PrizeClaim extends Model
{
    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
