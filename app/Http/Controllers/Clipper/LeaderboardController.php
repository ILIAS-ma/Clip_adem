<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportingService;
use Illuminate\View\View;

/**
 * Classement public, déjà construit pour le back-office — même service, même
 * chiffres, juste montré à ceux qu'il concerne plutôt qu'à l'administrateur
 * seul. Un classement que les clippeurs ne voient jamais ne motive personne.
 */
class LeaderboardController extends Controller
{
    public function index(ReportingService $reporting): View
    {
        return view('clipper.leaderboard', [
            // Un compte banni n'a rien à faire sur un classement public,
            // même s'il reste utile à l'administrateur de le voir dans le
            // sien : topClippersThisWeek() l'exclut déjà à la requête,
            // topClippers() non, donc filtré ici plutôt que dans le service
            // partagé avec le back-office.
            'topAllTime' => $reporting->topClippers(30)->reject->is_banned->take(20)->values(),
            'topThisWeek' => $reporting->topClippersThisWeek(20),
        ]);
    }
}
