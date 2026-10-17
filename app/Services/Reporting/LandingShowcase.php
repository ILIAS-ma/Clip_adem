<?php

namespace App\Services\Reporting;

use App\Enums\ClipStatus;
use App\Enums\UserRole;
use App\Models\Clip;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Ce que la page d'accueil montre du site réel : des compteurs et des clips
 * payés, plutôt que des promesses.
 */
class LandingShowcase
{
    public const CACHE_KEY = 'landing:showcase';

    /** Nombre de clips dans la vitrine. */
    public const WALL_SIZE = 10;

    /**
     * Compteurs et vitrine, en cache dix minutes : la page d'accueil est la
     * plus visitée du site, et ces chiffres n'ont pas besoin d'être à la
     * seconde près.
     *
     * @return array{stats: array{clippers: int, views: int, earned_cents: int}, clips: Collection<int, Clip>}
     */
    public function forLanding(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn () => [
            'stats' => $this->stats(),
            'clips' => $this->wall(),
        ]);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Mêmes définitions que les classements : vues des clips validés, gains
     * réellement crédités. Un compteur public qui gonflerait les chiffres
     * serait démenti par le premier clippeur qui fait le calcul.
     *
     * @return array{clippers: int, views: int, earned_cents: int}
     */
    public function stats(): array
    {
        $row = DB::table('clips')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN views_total ELSE 0 END), 0) as views', [ClipStatus::Approved->value])
            ->selectRaw('COALESCE(SUM(earned_cents), 0) as earned_cents')
            ->first();

        return [
            'clippers' => User::where('role', UserRole::Clipper)->where('is_banned', false)->count(),
            'views' => (int) $row->views,
            'earned_cents' => (int) $row->earned_cents,
        ];
    }

    /** @return Collection<int, Clip> */
    public function wall(): Collection
    {
        return Clip::query()
            ->whereNotNull('featured_at')
            ->where('status', ClipStatus::Approved)
            // Un clip dont la publication a disparu ne mène plus nulle part.
            ->whereNull('missing_since')
            ->whereHas('user', fn ($q) => $q->where('is_banned', false))
            ->orderByDesc('views_total')
            ->limit(self::WALL_SIZE)
            ->get(['id', 'platform', 'url', 'views_total', 'thumbnail_url', 'featured_thumbnail_path']);
    }

    /**
     * Met un clip en vitrine, en copiant sa miniature sur notre disque.
     *
     * Si la copie échoue, le clip est mis en avant quand même : la vitrine
     * sait afficher une case sans image, et l'administrateur n'a pas à
     * deviner pourquoi son clic n'a rien fait.
     */
    public function feature(Clip $clip): Clip
    {
        $path = $this->copyThumbnail($clip);

        $clip->forceFill([
            'featured_at' => now(),
            'featured_thumbnail_path' => $path ?? $clip->featured_thumbnail_path,
        ])->save();

        $this->forget();

        return $clip;
    }

    public function unfeature(Clip $clip): Clip
    {
        if ($clip->featured_thumbnail_path) {
            Storage::disk('public')->delete($clip->featured_thumbnail_path);
        }

        $clip->forceFill([
            'featured_at' => null,
            'featured_thumbnail_path' => null,
        ])->save();

        $this->forget();

        return $clip;
    }

    protected function copyThumbnail(Clip $clip): ?string
    {
        if (! $clip->thumbnail_url || ! str_starts_with($clip->thumbnail_url, 'https://')) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get($clip->thumbnail_url);
        } catch (\Throwable $exception) {
            Log::info('Miniature de vitrine injoignable', ['clip_id' => $clip->getKey(), 'error' => $exception->getMessage()]);

            return null;
        }

        $type = (string) $response->header('Content-Type');

        // Une page d'erreur HTML servie en 200 ne doit pas devenir une image.
        if (! $response->successful() || ! str_starts_with($type, 'image/')) {
            return null;
        }

        $extension = match (true) {
            str_contains($type, 'png') => 'png',
            str_contains($type, 'webp') => 'webp',
            default => 'jpg',
        };

        $path = "featured/clip-{$clip->getKey()}.{$extension}";
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }
}
