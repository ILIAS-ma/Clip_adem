<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Une période de classement, bornes incluses : du premier jour à 0 h au
 * dernier jour à minuit, dans le fuseau de l'application.
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'prize'])]
class Season extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeStarted($query)
    {
        return $query->whereDate('starts_on', '<=', today());
    }

    /** La saison en cours, ou null entre deux saisons. */
    public static function current(): ?self
    {
        return static::query()
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->orderByDesc('starts_on')
            ->first();
    }

    public function startsAt(): Carbon
    {
        return $this->starts_on->copy()->startOfDay();
    }

    public function endsAt(): Carbon
    {
        return $this->ends_on->copy()->endOfDay();
    }

    public function isRunning(): bool
    {
        return now()->between($this->startsAt(), $this->endsAt());
    }

    /** Jours restants, aujourd'hui compris. */
    public function daysLeft(): int
    {
        return max(0, (int) today()->diffInDays($this->ends_on, false) + 1);
    }
}
