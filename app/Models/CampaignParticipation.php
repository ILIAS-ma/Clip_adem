<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('campaign_participations')]
#[Fillable([
    'campaign_id', 'user_id', 'social_account_id', 'status',
    'applied_at', 'approved_at', 'rejection_reason',
])]
class CampaignParticipation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class, 'participation_id');
    }
}
