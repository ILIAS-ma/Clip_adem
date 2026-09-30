<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
|
| En local : `php artisan schedule:work`.
| En production : une entrée cron appelant `php artisan schedule:run`.
|
*/

/*
 * Relevé des vues.
 *
 * Ce passage ne relit pas tous les clips : `ClipSyncService::dueClips()` ne
 * rend que ceux dont l'intervalle personnel est écoulé — 30 minutes les deux
 * premiers jours, 3 heures la première semaine, puis une fois par jour.
 *
 * La fréquence du planificateur n'est donc pas la fréquence d'interrogation
 * d'un clip : c'est seulement la finesse avec laquelle on repère qui est dû.
 * Passer toutes les heures rendait le palier de 30 minutes fictif — un clip
 * dans sa fenêtre la plus vive était relevé deux fois moins souvent
 * qu'annoncé.
 *
 * Et comme le budget se distribue au premier arrivé, une heure de retard
 * transforme « premier arrivé » en « premier relevé » : entre deux clippeurs
 * d'une même campagne, c'est l'ordre de passage qui déciderait, pas leurs
 * vues.
 */
Schedule::command('clips:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Avant l'expiration, pas après : un jeton mort ne se découvre sinon qu'au
// moment où la synchronisation renvoie des 401.
Schedule::command('social:refresh-tokens')
    ->dailyAt('03:15')
    ->withoutOverlapping();

// Réconciliation des versements : rattrape les webhooks PayPal perdus.
Schedule::command('payouts:sync')
    ->hourly()
    ->withoutOverlapping();

// Filet comptable : signale toute divergence entre le grand livre et les
// compteurs dénormalisés.
Schedule::command('budget:audit')
    ->dailyAt('06:00');

/*
 * Vidange de la file d'attente.
 *
 * Les notifications aux clippeurs sont mises en file pour ne jamais retarder
 * — ni faire échouer — une décision de modération ou un versement. Elles
 * exigent donc un processus qui les consomme.
 *
 * `--stop-when-empty` plutôt qu'un démon permanent : sur un hébergement
 * mutualisé, il n'y a souvent aucun moyen de garder un processus vivant, et
 * une file qu'on croit traitée est pire que pas de file du tout. Quand un vrai
 * worker supervisé existera, cette ligne devient redondante et sans effet —
 * `withoutOverlapping` empêche les deux de se marcher dessus.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
