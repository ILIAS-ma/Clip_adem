<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le fuseau horaire de l'application.
 *
 * `config/app.php` figeait « UTC » en dur et ignorait APP_TIMEZONE, que le
 * `.env.example` annonce pourtant à Europe/Paris. Le réglage existait, était
 * documenté, et ne servait à rien.
 *
 * La conséquence s'est vue en production : un administrateur saisit « début de
 * diffusion : 19h50 » en heure de Paris, l'application compare à `now()` en
 * UTC, et la campagne refuse de créditer pendant deux heures de plus. Rien
 * n'explique pourquoi — `acceptsCredits()` répond simplement `false`.
 *
 * Le même décalage touchait les heures du planificateur et toutes les dates
 * affichées aux clippeurs.
 */
class TimezoneTest extends TestCase
{
    #[Test]
    public function the_configured_timezone_is_the_one_that_applies(): void
    {
        $previous = $_SERVER['APP_TIMEZONE'] ?? null;
        $_SERVER['APP_TIMEZONE'] = 'Europe/Paris';

        try {
            $config = require config_path('app.php');

            $this->assertSame('Europe/Paris', $config['timezone']);
        } finally {
            if ($previous === null) {
                unset($_SERVER['APP_TIMEZONE']);
            } else {
                $_SERVER['APP_TIMEZONE'] = $previous;
            }
        }
    }

    #[Test]
    public function the_setting_is_not_hard_coded(): void
    {
        /*
         * Ce test vise la régression exacte : quelqu'un qui « simplifie » en
         * remettant une chaîne littérale. Le fichier doit lire la variable
         * d'environnement, sans quoi le réglage redevient décoratif.
         */
        $source = file_get_contents(config_path('app.php'));

        $this->assertStringContainsString(
            "'timezone' => env('APP_TIMEZONE'",
            $source,
            'Le fuseau doit être lu depuis APP_TIMEZONE, pas figé dans le fichier.',
        );
    }
}
