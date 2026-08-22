<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relevé de vues à un instant donné. Chaque insertion est l'événement qui
 * déclenche un appel à CampaignBudgetService::creditViews(), avec l'identifiant
 * du snapshot comme clé d'idempotence.
 */
#[Fillable(['clip_id', 'views', 'source', 'captured_at'])]
class ClipViewSnapshot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function clip(): BelongsTo
    {
        return $this->belongsTo(Clip::class);
    }
}
