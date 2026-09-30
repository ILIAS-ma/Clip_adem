<?php

namespace Tests\Feature\Social;

use App\Services\Social\TikTokProvider;
use App\Services\Social\YouTubeProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le quota journalier d'appels.
 *
 * `TIKTOK_DAILY_QUOTA=` — une ligne que `.env.example` livre vide — ne vaut pas
 * `null` mais la chaîne vide. `dailyQuota(): ?int` levait donc une TypeError,
 * et le relevé des vues s'arrêtait net avant d'interroger quoi que ce soit.
 *
 * La panne est restée invisible tant que la synchronisation échouait plus tôt,
 * sur une portée manquante. Elle est apparue le jour où le reste s'est mis à
 * marcher — c'est-à-dire au pire moment.
 */
class DailyQuotaTest extends TestCase
{
    /** Relit la configuration avec la variable d'environnement voulue. */
    protected function avec(string $variable, ?string $valeur): array
    {
        $precedent = $_SERVER[$variable] ?? null;

        if ($valeur === null) {
            unset($_SERVER[$variable]);
        } else {
            $_SERVER[$variable] = $valeur;
        }

        try {
            return require config_path('services.php');
        } finally {
            if ($precedent === null) {
                unset($_SERVER[$variable]);
            } else {
                $_SERVER[$variable] = $precedent;
            }
        }
    }

    #[Test]
    public function an_empty_variable_means_no_quota(): void
    {
        $services = $this->avec('TIKTOK_DAILY_QUOTA', '');

        $this->assertNull($services['tiktok']['daily_quota']);
    }

    #[Test]
    public function a_number_is_read_as_a_number(): void
    {
        // Une variable d'environnement est toujours une chaîne : sans cast,
        // « 500 » traverserait la configuration en texte et se comparerait
        // n'importe comment à un compteur d'appels.
        $services = $this->avec('TIKTOK_DAILY_QUOTA', '500');

        $this->assertSame(500, $services['tiktok']['daily_quota']);
    }

    #[Test]
    public function youtube_keeps_its_default_when_nothing_is_declared(): void
    {
        $services = $this->avec('YOUTUBE_DAILY_QUOTA', '');

        $this->assertSame(10_000, $services['youtube']['daily_quota']);
    }

    #[Test]
    public function the_providers_honour_their_declared_return_type(): void
    {
        /*
         * Le vrai garde-fou : c'est l'appel à `dailyQuota()` qui explosait, pas
         * la lecture de la configuration. On l'exerce donc pour de bon, avec la
         * valeur vide qui a provoqué la panne.
         */
        config(['services.tiktok.daily_quota' => null]);
        config(['services.youtube.daily_quota' => 10_000]);

        $this->assertNull(app(TikTokProvider::class)->dailyQuota());
        $this->assertSame(10_000, app(YouTubeProvider::class)->dailyQuota());
    }
}
