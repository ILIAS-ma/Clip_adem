<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sender_id', 'from_staff', 'body', 'read_at'])]
class Message extends Model
{
    /** Assez pour expliquer un problème de versement, pas pour coller un roman. */
    public const MAX_LENGTH = 2000;

    protected function casts(): array
    {
        return [
            'from_staff' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }
}
