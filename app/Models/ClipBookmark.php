<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un clip repéré, mis de côté par un clippeur.
 *
 * Ce n'est pas un `Clip` : un `Clip` est une publication que nous suivons et
 * rémunérons, celui-ci n'est qu'une note de repérage sur un moment de direct
 * qui n'a encore été ni monté ni publié. Les confondre amènerait à créditer un
 * budget pour une intention.
 */
#[Fillable([
    'user_id', 'source', 'external_id', 'url', 'title', 'broadcaster',
    'views', 'duration_seconds', 'thumbnail_url', 'campaign_id', 'taken_at',
])]
class ClipBookmark extends Model
{
    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'duration_seconds' => 'integer',
            'taken_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    public function isTaken(): bool
    {
        return $this->taken_at !== null;
    }

    public function dureeLisible(): string
    {
        return sprintf('%d:%02d', intdiv($this->duration_seconds, 60), $this->duration_seconds % 60);
    }
}
