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

// Relevé des vues. La cadence réelle par clip est dégressive et gérée dans
// ClipSyncService : ce passage horaire ne fait que réveiller ceux qui sont dus.
Schedule::command('clips:sync')
    ->hourly()
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
