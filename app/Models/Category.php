<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Univers de campagnes défini par l'administration, base des classements par
 * catégorie.
 */
#[Fillable(['name', 'slug', 'position'])]
class Category extends Model
{
    use HasFactory;

    /**
     * L'identifiant ne sert qu'au lien de l'onglet du classement : il se
     * fabrique depuis le nom à la création, et ne bouge plus ensuite pour ne
     * pas casser les liens déjà partagés.
     */
    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (blank($category->slug)) {
                $category->slug = static::uniqueSlug((string) $category->name);
            }
        });
    }

    /** « Gaming », puis « gaming-2 »… */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'categorie';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

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
