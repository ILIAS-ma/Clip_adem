<?php

namespace App\Services\Settings;

use App\Enums\ModerationAction;
use App\Models\ModerationLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Les réglages que l'équipe change depuis l'administration.
 *
 * Toute la configuration du site vit dans des fichiers et dans le .env : la modifier demande un accès au
 * serveur. Ce service y ajoute une couche : au démarrage, les valeurs enregistrées en base viennent
 * recouvrir celles de la configuration, clé par clé. Tout le reste du code continue de lire
 * `config('clipping.referrals.rate_bp')` et obtient, sans rien savoir, la valeur choisie par l'équipe.
 *
 * Seules les clés listées dans KEYS sont acceptées. Un réglage supprimé de la base retombe sur la valeur
 * de la configuration d'origine.
 */
class SiteSettings
{
    private const CACHE_KEY = 'site-settings';

    /** Clé du réglage => [chemin de configuration, type]. */
    public const KEYS = [
        'referrals.enabled' => ['clipping.referrals.enabled', 'bool'],
        'referrals.rate_bp' => ['clipping.referrals.rate_bp', 'int'],
        'referrals.active_min_clips' => ['clipping.referrals.active_min_clips', 'int'],
        'referrals.active_min_views' => ['clipping.referrals.active_min_views', 'int'],
        'referrals.active_min_days' => ['clipping.referrals.active_min_days', 'int'],
        'referrals.leaderboard_prize' => ['clipping.referrals.leaderboard_prize', 'string'],
    ];

    /** Les valeurs d'origine de la configuration, gardées avant tout recouvrement. @var array<string, mixed> */
    private static array $original = [];

    /** Applique les réglages enregistrés par-dessus la configuration. À appeler au démarrage. */
    public function apply(): void
    {
        foreach (self::KEYS as $key => [$path]) {
            self::$original[$key] ??= config($path);
        }

        foreach ($this->stored() as $key => $value) {
            if (isset(self::KEYS[$key])) {
                config([self::KEYS[$key][0] => $this->cast($value, self::KEYS[$key][1])]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, fn () => Setting::query()->pluck('value', 'key')
                ->map(fn ($raw) => json_decode((string) $raw, true))
                ->all());
        } catch (\Throwable) {
            // La table n'existe pas encore (migration en cours, premier démarrage) : la configuration d'origine fait foi.
            return [];
        }
    }

    /** La valeur d'origine d'un réglage, celle de la configuration, avant tout changement de l'équipe. */
    public function original(string $key): mixed
    {
        return self::$original[$key] ?? config(self::KEYS[$key][0]);
    }

    /**
     * Enregistre des réglages, journalise ce qui a changé, et les applique tout de suite.
     *
     * @param  array<string, mixed>  $values  clé du réglage => nouvelle valeur
     */
    public function save(array $values, User $by): void
    {
        $changes = [];

        foreach ($values as $key => $value) {
            if (! isset(self::KEYS[$key])) {
                throw new InvalidArgumentException("Réglage inconnu : $key");
            }

            $value = $this->cast($value, self::KEYS[$key][1]);
            // Converti comme la nouvelle valeur : une valeur lue dans le .env est un texte (« 500 »), pas un nombre.
            $before = $this->cast(config(self::KEYS[$key][0]), self::KEYS[$key][1]);

            if ($before === $value) {
                continue;
            }

            Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value), 'updated_by' => $by->getKey()]);
            config([self::KEYS[$key][0] => $value]);
            $changes[$key] = ['avant' => $before, 'après' => $value];
        }

        Cache::forget(self::CACHE_KEY);

        if ($changes !== []) {
            ModerationLog::record(
                ModerationAction::SettingsChanged,
                $by,
                $by,
                'Réglages modifiés : '.implode(', ', array_keys($changes)),
                ['changes' => $changes],
            );
        }
    }

    /** Revient aux valeurs d'origine : supprime les réglages enregistrés pour ces clés. */
    public function reset(array $keys, User $by): void
    {
        $changed = [];

        foreach ($keys as $key) {
            if (! isset(self::KEYS[$key])) {
                throw new InvalidArgumentException("Réglage inconnu : $key");
            }

            if (Setting::where('key', $key)->delete()) {
                config([self::KEYS[$key][0] => $this->original($key)]);
                $changed[] = $key;
            }
        }

        Cache::forget(self::CACHE_KEY);

        if ($changed !== []) {
            ModerationLog::record(ModerationAction::SettingsChanged, $by, $by, 'Réglages remis par défaut : '.implode(', ', $changed));
        }
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            default => blank($value) ? null : trim((string) $value),
        };
    }
}
