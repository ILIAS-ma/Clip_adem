<?php

namespace Tests\Feature\Social;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le planificateur doit se réveiller au moins aussi souvent que la cadence
 * la plus courte qu'on annonce.
 *
 * La configuration déclarait 30 minutes pour un clip de moins de deux jours —
 * sa fenêtre la plus vive. Le planificateur, lui, ne passait qu'une fois par
 * heure : ce palier n'a jamais existé, et rien ne le signalait. Les deux
 * réglages vivent dans des fichiers différents, chacun cohérent seul.
 *
 * L'enjeu n'est pas la fraîcheur d'un compteur. Le budget d'une campagne se
 * distribue au premier arrivé : quand plusieurs clippeurs travaillent sur la
 * même campagne, un retard de relevé transforme « premier arrivé » en
 * « premier relevé ». Ce n'est plus leurs vues qui décident, c'est l'ordre de
 * passage d'une tâche planifiée.
 */
class SyncCadenceTest extends TestCase
{
    /** L'expression cron de la tâche de relevé. */
    protected function expression(string $commande): string
    {
        $evenement = collect(app(Schedule::class)->events())
            ->first(fn (Event $e) => str_contains($e->command ?? '', $commande));

        $this->assertNotNull($evenement, "La tâche « {$commande} » n’est pas planifiée.");

        return $evenement->expression;
    }

    /** Intervalle, en minutes, d'une expression cron du type « *\/5 * * * * ». */
    protected function intervalleEnMinutes(string $expression): int
    {
        $minutes = explode(' ', $expression)[0];

        if (str_starts_with($minutes, '*/')) {
            return (int) substr($minutes, 2);
        }

        // « 0 * * * * » : une fois par heure, à la minute zéro.
        return $minutes === '*' ? 1 : 60;
    }

    #[Test]
    public function the_scheduler_keeps_up_with_the_shortest_declared_interval(): void
    {
        $planifie = $this->intervalleEnMinutes($this->expression('clips:sync'));
        $annonce = (int) config('clipping.sync.hot_interval_minutes');

        $this->assertLessThanOrEqual(
            $annonce,
            $planifie,
            "Le planificateur passe toutes les {$planifie} min alors que la cadence "
            ."la plus courte est de {$annonce} min : ce palier est fictif.",
        );
    }

    #[Test]
    public function the_accounting_net_still_runs_once_a_day(): void
    {
        /*
         * L'inverse du test précédent : tout ne doit pas devenir fréquent.
         * `budget:audit` relit l'intégralité du grand livre ; le faire tourner
         * en boucle coûterait cher pour un contrôle dont la valeur est de
         * passer régulièrement, pas souvent.
         */
        $this->assertSame(60, $this->intervalleEnMinutes($this->expression('budget:audit')));
    }

    #[Test]
    public function running_more_often_does_not_poll_a_clip_more_often(): void
    {
        /*
         * La distinction qui rend le changement sans risque pour le quota : la
         * fréquence du planificateur n'est pas la fréquence d'interrogation
         * d'un clip. `dueClips()` ne rend que ceux dont l'intervalle personnel
         * est écoulé — passer plus souvent affine seulement le repérage.
         */
        $source = file_get_contents(app_path('Services/Social/ClipSyncService.php'));

        $this->assertMatchesRegularExpression(
            '/protected function isDue\(/',
            $source,
            'La cadence par clip doit rester décidée dans le service, pas par le planificateur.',
        );
    }
}
