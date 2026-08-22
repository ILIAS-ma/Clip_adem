<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'platform', 'rate_per_1k_cents', 'is_enabled'])]
class CampaignRate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'rate_per_1k_cents' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
