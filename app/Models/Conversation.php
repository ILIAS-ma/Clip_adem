<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Le fil entre un utilisateur et l'équipe. Un seul par utilisateur.
 */
#[Fillable(['user_id', 'last_message_at'])]
class Conversation extends Model
{
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public static function for(User $user): self
    {
        return static::firstOrCreate(['user_id' => $user->getKey()]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** Messages que l'équipe n'a pas encore lus. */
    public function unreadForStaff(): HasMany
    {
        return $this->messages()->where('from_staff', false)->whereNull('read_at');
    }

    /** Messages que l'utilisateur n'a pas encore lus. */
    public function unreadForUser(): HasMany
    {
        return $this->messages()->where('from_staff', true)->whereNull('read_at');
    }

    public function post(User $sender, string $body): Message
    {
        $message = $this->messages()->create([
            'sender_id' => $sender->getKey(),
            'from_staff' => $sender->isStaff(),
            'body' => $body,
        ]);

        $this->forceFill(['last_message_at' => $message->created_at])->save();

        return $message;
    }

    /** Marque lu ce que `$reader` a reçu : ce qui vient de l'autre côté du fil. */
    public function markReadBy(User $reader): void
    {
        ($reader->isStaff() ? $this->unreadForStaff() : $this->unreadForUser())
            ->update(['read_at' => now()]);
    }
}
