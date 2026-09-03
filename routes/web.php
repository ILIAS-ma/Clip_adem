<?php

use App\Http\Controllers\Artist\ArtistCampaignController;
use App\Http\Controllers\Artist\ArtistDashboardController;
use App\Http\Controllers\Artist\ArtistProfileController;
use App\Http\Controllers\Clipper\CampaignController;
use App\Http\Controllers\Clipper\ClipController;
use App\Http\Controllers\Clipper\DashboardController;
use App\Http\Controllers\Clipper\EarningsController;
use App\Http\Controllers\Clipper\ProfileCompletionController;
use App\Http\Controllers\Clipper\SocialAccountController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Chaque rôle est renvoyé chez lui : la page d'accueil n'a d'intérêt que
    // pour un visiteur non connecté.
    return auth()->check()
        ? redirect(auth()->user()->role->homeRoute())
        : view('welcome');
})->name('home');

// Retours asynchrones de PayPal sur les versements. Hors session et hors CSRF :
// l'authenticité est établie par la signature de la requête (voir le contrôleur).
Route::post('/webhooks/paypal', PayPalWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paypal');

/*
|--------------------------------------------------------------------------
| Commun aux comptes connectés
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.banned'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Espace clippeur
|--------------------------------------------------------------------------
|
| `not.banned` déconnecte un compte suspendu plutôt que de le laisser naviguer
| avec une session déjà ouverte ; `role:clipper` renvoie les autres profils
| vers leur propre espace au lieu de leur montrer un 403 sans issue.
|
*/
Route::middleware(['auth', 'not.banned', 'role:clipper'])->group(function () {

    // Hors du groupe « profil complet », sinon la redirection boucle sur elle-même.
    Route::get('/profil/completer', [ProfileCompletionController::class, 'edit'])->name('profile.complete');
    Route::patch('/profil/completer', [ProfileCompletionController::class, 'update'])->name('profile.complete.update');

    Route::middleware(['verified', 'profile.completed'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/campagnes', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campagnes/{campaign:slug}', [CampaignController::class, 'show'])->name('campaigns.show');

        Route::get('/mes-clips', [ClipController::class, 'index'])->name('clips.index');
        Route::get('/mes-clips/{clip}', [ClipController::class, 'show'])->name('clips.show');

        Route::get('/mes-comptes', [SocialAccountController::class, 'index'])->name('accounts.index');
        Route::get('/mes-comptes/{platform}/connexion', [SocialAccountController::class, 'redirect'])
            ->name('social.redirect');
        Route::delete('/mes-comptes/{account}', [SocialAccountController::class, 'destroy'])
            ->name('accounts.destroy');

        Route::get('/revenus', [EarningsController::class, 'index'])->name('earnings.index');
    });
});

/*
|--------------------------------------------------------------------------
| Espace artiste
|--------------------------------------------------------------------------
|
| Consultation seule : l'artiste suit ses campagnes, il ne les crée ni ne les
| modifie. Le budget et la modération restent le métier de l'administrateur.
|
*/
Route::middleware(['auth', 'not.banned', 'role:artist', 'verified'])
    ->prefix('artiste')
    ->name('artist.')
    ->group(function () {

        // Hors du garde « fiche existante », sinon la redirection boucle.
        Route::get('/profil/creer', [ArtistProfileController::class, 'create'])->name('profile.create');
        Route::post('/profil/creer', [ArtistProfileController::class, 'store'])->name('profile.store');

        Route::middleware('artist.profile')->group(function () {
            Route::get('/', ArtistDashboardController::class)->name('dashboard');
            Route::get('/campagnes/{campaign:slug}', [ArtistCampaignController::class, 'show'])->name('campaigns.show');

            Route::get('/profil', [ArtistProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/profil', [ArtistProfileController::class, 'update'])->name('profile.update');
        });
    });

// Retour du fournisseur OAuth. Hors du groupe « profil complet » : le
// fournisseur redirige vers une URL fixe, et une redirection intermédiaire
// invaliderait le code d'autorisation.
Route::get('/oauth/{platform}/callback', [SocialAccountController::class, 'callback'])
    ->middleware(['auth', 'not.banned', 'role:clipper'])
    ->name('social.callback');

require __DIR__.'/auth.php';
