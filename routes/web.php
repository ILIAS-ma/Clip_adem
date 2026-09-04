<?php

use App\Http\Controllers\Clipper\CampaignController;
use App\Http\Controllers\Clipper\ClipController;
use App\Http\Controllers\Clipper\DashboardController;
use App\Http\Controllers\Clipper\EarningsController;
use App\Http\Controllers\Clipper\PayoutMethodController;
use App\Http\Controllers\Clipper\ProfileCompletionController;
use App\Http\Controllers\Clipper\SocialAccountController;
use App\Http\Controllers\Creator\CampaignController as CreatorCampaignController;
use App\Http\Controllers\Creator\DashboardController as CreatorDashboardController;
use App\Http\Controllers\Creator\ProfileController as CreatorProfileController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/**
 * Passages obligés du parcours, pilotés depuis config/clipping.php.
 *
 * Les suspendre laisse parcourir l'interface sans obstacle en développement,
 * sans commenter ni supprimer le moindre contrôle : le code reste actif, et la
 * suite de tests les force à `true` pour continuer de les vérifier.
 *
 * @return array<int, string>
 */
$onboardingGuards = static fn (): array => array_values(array_filter([
    config('clipping.onboarding.require_email_verification') ? 'verified' : null,
    config('clipping.onboarding.require_complete_profile') ? 'profile.completed' : null,
]));

/*
 * La racine est l'écran de connexion.
 *
 * Le formulaire est rendu directement plutôt que redirigé vers /login : une
 * redirection ajouterait un aller-retour à chaque arrivée sur le site, y
 * compris après déconnexion.
 *
 * Un utilisateur déjà connecté repart vers son propre espace.
 */
Route::get('/', function () {
    return auth()->check()
        ? redirect(auth()->user()->role->homeRoute())
        : view('auth.login');
})->name('home');

// La page de présentation garde son contenu — brief du fonctionnement,
// avertissement sur le budget qui part au premier arrivé — accessible depuis
// l'écran de connexion.
Route::get('/presentation', fn () => view('welcome'))->name('presentation');

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
Route::middleware(['auth', 'not.banned', 'role:clipper'])->group(function () use ($onboardingGuards) {

    // Hors du groupe « profil complet », sinon la redirection boucle sur elle-même.
    Route::get('/profil/completer', [ProfileCompletionController::class, 'edit'])->name('profile.complete');
    Route::patch('/profil/completer', [ProfileCompletionController::class, 'update'])->name('profile.complete.update');

    Route::middleware($onboardingGuards())->group(function () {
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

        Route::get('/revenus/paiement', [PayoutMethodController::class, 'edit'])->name('payout-method.edit');
        Route::patch('/revenus/paiement', [PayoutMethodController::class, 'update'])->name('payout-method.update');
    });
});

/*
|--------------------------------------------------------------------------
| Espace créateur
|--------------------------------------------------------------------------
|
| Consultation seule : le créateur suit ses campagnes, il ne les crée ni ne les
| modifie. Le budget et la modération restent le métier de l'administrateur.
|
*/
Route::middleware(array_merge(
    ['auth', 'not.banned', 'role:creator'],
    config('clipping.onboarding.require_email_verification') ? ['verified'] : [],
))
    ->prefix('createur')
    ->name('creator.')
    ->group(function () {

        // Hors du garde « fiche existante », sinon la redirection boucle.
        Route::get('/profil/creer', [CreatorProfileController::class, 'create'])->name('profile.create');
        Route::post('/profil/creer', [CreatorProfileController::class, 'store'])->name('profile.store');

        Route::middleware('creator.profile')->group(function () {
            Route::get('/', CreatorDashboardController::class)->name('dashboard');
            Route::get('/campagnes/{campaign:slug}', [CreatorCampaignController::class, 'show'])->name('campaigns.show');

            Route::get('/profil', [CreatorProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/profil', [CreatorProfileController::class, 'update'])->name('profile.update');
        });
    });

// Retour du fournisseur OAuth. Hors du groupe « profil complet » : le
// fournisseur redirige vers une URL fixe, et une redirection intermédiaire
// invaliderait le code d'autorisation.
Route::get('/oauth/{platform}/callback', [SocialAccountController::class, 'callback'])
    ->middleware(['auth', 'not.banned', 'role:clipper'])
    ->name('social.callback');

require __DIR__.'/auth.php';
