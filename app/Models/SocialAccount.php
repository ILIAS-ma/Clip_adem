<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'platform', 'external_account_id', 'handle',
    'access_token', 'refresh_token', 'token_expires_at',
    'followers_count', 'verified_at', 'is_active',
])]
#[Hidden(['access_token', 'refresh_token'])]
class SocialAccount extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            // Chiffrement au repos : une fuite de dump ne doit pas livrer les
            // comptes TikTok/YouTube des clippeurs.
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'followers_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }
}
