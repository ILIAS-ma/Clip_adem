<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Season;
use App\Services\Reporting\ReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Classement public, déjà construit pour le back-office — même service, même
 * chiffres, juste montré à ceux qu'il concerne plutôt qu'à l'administrateur
 * seul. Un classement que les clippeurs ne voient jamais ne motive personne.
 */
class LeaderboardController extends Controller
{
    public function index(Request $request, ReportingService $reporting): View
    {
        $categories = Category::query()->ordered()->get();

        /*
         * La catégorie affichée passe par l'adresse plutôt que par un onglet
         * JavaScript : le lien se partage, et seule la catégorie regardée est
         * calculée. Un identifiant inconnu retombe sur la première plutôt que
         * sur une erreur — c'est un lien périmé, pas une faute.
         */
        $category = $categories->firstWhere('slug', $request->query('categorie'))
            ?? $categories->first();

        /*
         * Saisons commencées, la plus récente d'abord. Par défaut la saison en
         * cours ; entre deux saisons, la dernière terminée — son podium reste
         * intéressant tant que la suivante n'a pas commencé.
         */
        $seasons = Season::query()->started()->orderByDesc('starts_on')->get();
        $season = $seasons->firstWhere('id', (int) $request->query('saison'))
            ?? Season::current()
            ?? $seasons->first();

        return view('clipper.leaderboard', [
            // Un compte banni n'a rien à faire sur un classement public,
            // même s'il reste utile à l'administrateur de le voir dans le
            // sien : topClippersThisWeek() l'exclut déjà à la requête,
            // topClippers() non, donc filtré ici plutôt que dans le service
            // partagé avec le back-office.
            'topAllTime' => $reporting->topClippers(30)->reject->is_banned->take(20)->values(),
            'topThisWeek' => $reporting->topClippersThisWeek(20),
            'categories' => $categories,
            'category' => $category,
            'topInCategory' => $category ? $reporting->topClippersInCategory($category, 20) : collect(),
            'categoryTotals' => $category ? $reporting->categoryTotals($category) : null,
            'seasons' => $seasons,
            'season' => $season,
            'topInSeason' => $season ? $reporting->topClippersInSeason($season, 20) : collect(),
            'topReferrers' => config('clipping.referrals.enabled') ? $reporting->topReferrers(10) : collect(),
        ]);
    }
}
