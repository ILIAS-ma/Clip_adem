<?php

use App\Http\Controllers\Clipper\CampaignController;
use App\Http\Controllers\Clipper\ClipController;
use App\Http\Controllers\Clipper\DashboardController;
use App\Http\Controllers\Clipper\EarningsController;
use App\Http\Controllers\Clipper\PayoutMethodController;
use App\Http\Controllers\Clipper\ProfileCompletionController;
use App\Http\Controllers\Clipper\ReferralController;
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
 * La racine est la landing page publique — brief du fonctionnement,
 * avertissement sur le budget qui part au premier arrivé. L'écran de
 * connexion vit sur /login (voir routes/auth.php).
 *
 * Un utilisateur déjà connecté repart vers son propre espace : il n'a rien à
 * faire sur une page de présentation destinée aux visiteurs.
 */
Route::get('/', function () {
    return auth()->check()
        ? redirect(auth()->user()->role->homeRoute())
        : view('welcome');
})->name('home');

// Ancienne URL de la landing page, conservée pour ne pas casser les liens
// déjà partagés.
Route::redirect('/presentation', '/');

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
| Pages légales
|--------------------------------------------------------------------------
|
| Publiques et sans état : elles sont lues par des humains, mais aussi par les
| vérificateurs de TikTok et de Google, qui n'ont ni session ni cookie. Les
| placer derrière le moindre garde-fou ferait échouer une revue d'application.
|
| Les URL sont en anglais parce qu'elles sont saisies dans des consoles de
| développeurs anglophones, où « conditions-dutilisation » se recopie mal.
|
*/
Route::view('/terms', 'legal.terms', [
    'updatedAt' => '7 septembre 2026',
    'contactEmail' => config('mail.from.address'),
])->name('legal.terms');

Route::view('/privacy', 'legal.privacy', [
    'updatedAt' => '7 septembre 2026',
    'contactEmail' => config('mail.from.address'),
])->name('legal.privacy');

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

        // Débité en plus du délai de garde : celui-ci protège le quota d'API,
        // celui-là protège le serveur d'un clic répété.
        Route::post('/mes-clips/{clip}/actualiser', [ClipController::class, 'refresh'])
            ->middleware('throttle:20,1')
            ->name('clips.refresh');

        // Même circuit que ci-dessus, format JSON pour l'interface plutôt
        // qu'une redirection avec message flash.
        Route::get('/mes-clips/{clip}/analyse', [ClipController::class, 'analyze'])
            ->middleware('throttle:20,1')
            ->name('clips.analyze');

        Route::get('/mes-comptes', [SocialAccountController::class, 'index'])->name('accounts.index');
        Route::get('/mes-comptes/{platform}/connexion', [SocialAccountController::class, 'redirect'])
            ->name('social.redirect');
        Route::delete('/mes-comptes/{account}', [SocialAccountController::class, 'destroy'])
            ->name('accounts.destroy');

        Route::get('/revenus', [EarningsController::class, 'index'])->name('earnings.index');

        Route::get('/parrainage', ReferralController::class)->name('referrals.index');

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
