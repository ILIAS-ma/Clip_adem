<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'platform', 'started_at', 'finished_at', 'clips_synced',
    'api_calls', 'quota_used', 'rate_limited', 'error',
])]
class SocialSyncRun extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'clips_synced' => 'integer',
            'api_calls' => 'integer',
            'quota_used' => 'integer',
            'rate_limited' => 'boolean',
        ];
    }

    /** Quota consommé aujourd'hui par une plateforme, pour décider d'y aller ou non. */
    public static function quotaUsedToday(Platform $platform): int
    {
        return (int) static::where('platform', $platform)
            ->where('started_at', '>=', now()->startOfDay())
            ->sum('quota_used');
    }
}
