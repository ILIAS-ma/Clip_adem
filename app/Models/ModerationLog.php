<?php

namespace App\Models;

use App\Enums\ModerationAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'reason', 'meta'])]
class ModerationLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ModerationAction::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(
        ModerationAction $action,
        Model $subject,
        ?User $by = null,
        ?string $reason = null,
        array $meta = [],
    ): self {
        return static::create([
            'user_id' => $by?->getKey(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'reason' => $reason,
            'meta' => $meta ?: null,
        ]);
    }
}
