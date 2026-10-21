<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Une leçon de l'espace formation.
 */
#[Fillable([
    'title', 'slug', 'summary', 'body', 'video_path', 'video_url',
    'duration_minutes', 'position', 'is_published',
])]
class TrainingLesson extends Model
{
    use HasFactory;

    /**
     * Où le script d'enregistrement dépose les vidéos du parcours, sous le
     * nom de la leçon : `formation/{slug}.webm`. Une leçon sans vidéo
     * déposée à la main reprend automatiquement celle-là.
     */
    public const RECORDED_DIRECTORY = 'formation';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'position' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function completions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'training_lesson_user')
            ->withPivot('completed_at');
    }

    /** @param  Builder<self>  $query */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function isCompletedBy(User $user): bool
    {
        return $this->completions()->whereKey($user->getKey())->exists();
    }

    /**
     * Fichier vidéo servi par le site, dans cet ordre : celui déposé dans le
     * back-office, puis celui produit par le script d'enregistrement.
     */
    public function videoFileUrl(): ?string
    {
        $disk = Storage::disk('public');

        if ($this->video_path && $disk->exists($this->video_path)) {
            return $disk->url($this->video_path);
        }

        foreach (['webm', 'mp4'] as $extension) {
            $recorded = self::RECORDED_DIRECTORY."/{$this->slug}.{$extension}";

            if ($disk->exists($recorded)) {
                return $disk->url($recorded);
            }
        }

        return null;
    }

    /**
     * Lien YouTube transformé en adresse d'intégration : coller l'adresse de
     * la page, celle qu'on a sous la main, doit suffire.
     */
    public function embedUrl(): ?string
    {
        if (! $this->video_url) {
            return null;
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $this->video_url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $this->video_url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }

    /**
     * La « vidéo » est-elle une page animée avec sa voix off (un export HTML
     * de Claude Design) plutôt qu'un fichier mp4 ? Elle s'affiche alors dans
     * un cadre, avec son propre lecteur.
     */
    public function isInteractivePage(): bool
    {
        return $this->video_path !== null && str_ends_with(strtolower($this->video_path), '.html');
    }

    public function hasVideo(): bool
    {
        return $this->videoFileUrl() !== null || $this->embedUrl() !== null;
    }

    /**
     * Le texte de la leçon, rendu en HTML.
     *
     * Le HTML brut saisi est retiré : le contenu vient du back-office, mais un
     * compte modérateur compromis ne doit pas pouvoir glisser un script sur la
     * page de tous les clippeurs.
     */
    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }
}
