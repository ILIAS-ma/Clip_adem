<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Univers de campagnes défini par l'administration, base des classements par
 * catégorie.
 */
#[Fillable(['name', 'slug', 'position'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('name');
    }
}
