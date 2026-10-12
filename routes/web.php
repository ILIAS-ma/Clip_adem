<?php

use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Clipper\AchievementController;
use App\Http\Controllers\Clipper\CampaignController;
use App\Http\Controllers\Clipper\ClipController;
use App\Http\Controllers\Clipper\DashboardController;
use App\Http\Controllers\Clipper\EarningsController;
use App\Http\Controllers\Clipper\LeaderboardController;
use App\Http\Controllers\Clipper\PayoutMethodController;
use App\Http\Controllers\Clipper\ProfileCompletionController;
use App\Http\Controllers\Clipper\ReferralController;
use App\Http\Controllers\Clipper\SocialAccountController;
use App\Http\Controllers\Creator\CampaignController as CreatorCampaignController;
use App\Http\Controllers\Creator\DashboardController as CreatorDashboardController;
use App\Http\Controllers\Creator\ProfileController as CreatorProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    /*
     * Toujours montée, contrairement aux autres : c'est le middleware qui lit
     * le réglage, à chaque requête. Filtrer ici figerait le choix pour toute
     * la durée du processus.
     *
     * Placée avant la complétion du profil : le numéro est ce qui distingue
     * une personne d'un compte jetable, et demander une adresse PayPal à
     * quelqu'un dont on ne sait rien revient à préparer un versement vers un
     * compte qu'on ne pourra pas rattacher.
     */
    'phone.verified',

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
| Garde d'accès de FetchClip
|--------------------------------------------------------------------------
|
| FetchClip tourne comme service Node à côté du site, sur 127.0.0.1:3000, et
| n'a aucune authentification : il a été écrit pour une seule machine, la
| sienne. Nginx lui en pose une devant, en interrogeant cette route avant
| chaque requête (`auth_request`).
|
| Elle répond 204 ou 401, jamais une redirection : `auth_request` traite tout
| ce qui n'est pas 2xx comme un refus, et une redirection vers l'écran de
| connexion serait comprise comme une panne.
|
| Hors des groupes `auth` : le middleware redirigerait un visiteur anonyme au
| lieu de le refuser.
|
*/
Route::get('/_studio-auth', function () {
    $user = auth()->user();

    abort_unless($user && $user->isClipper() && ! $user->is_banned, 401);

    /*
     * L'identité repart dans un en-tête, que Nginx relaie à l'outil : c'est
     * elle qui décide de l'espace de travail où ses fichiers atterrissent.
     *
     * Elle vient d'ici et non du navigateur, et c'est tout l'intérêt : un
     * en-tête que le client pourrait poser lui-même laisserait n'importe
     * qui lire l'espace de n'importe qui en changeant un chiffre.
     */
    return response()->noContent()->header('X-Clipper-Id', 'c'.$user->getKey());
})->name('studio.auth');

/*
 * Garder la session vivante pendant qu'on travaille dans FetchClip.
 *
 * Nginx vérifie la session par une sous-requête dont la réponse — cookie
 * compris — ne revient jamais au navigateur. Le cookie n'était donc jamais
 * renouvelé : deux heures après la dernière page du site, on était
 * déconnecté en plein montage, et l'outil continuait de tourner sur des
 * réponses vides. Le symptôme était « undefined » partout, jamais
 * « reconnecte-toi ».
 *
 * Cette route, elle, répond directement au navigateur : son cookie
 * renouvelé arrive. L'outil l'appelle à intervalle régulier.
 */
Route::get('/_studio-ping', fn () => response()->noContent())
    ->middleware(['auth', 'throttle:30,1'])
    ->name('studio.ping');

/*
 * Retour vers l'outil après une reconnexion.
 *
 * Sans elle, une session expirée ramenait à l'écran de connexion puis au
 * tableau de bord, et il fallait retrouver le chemin de l'outil — en ayant
 * perdu de vue ce qu'on faisait.
 */
Route::get('/connexion-studio', function () {
    session()->put('url.intended', url('/fetchclip'));

    return redirect()->route('login');
})->name('studio.login');

/*
 * Les erreurs du navigateur, remontées dans nos journaux.
 *
 * FetchClip tourne entièrement côté client : une exception y reste dans une
 * console que nous ne voyons pas, et le symptôme qui nous parvient est « ça
 * ne marche pas ». Diagnostiquer revenait à deviner, puis à faire deviner la
 * personne en face.
 *
 * Débitée, et volontairement pauvre : trois champs, tronqués. Un journal
 * qu'on peut remplir depuis l'extérieur est un journal qu'on peut noyer.
 */
Route::post('/_studio-log', function (Request $request) {
    abort_unless(auth()->user()?->isClipper(), 404);

    Log::warning('Erreur dans le mode créateur', [
        'user_id' => auth()->id(),
        'message' => mb_substr((string) $request->input('message'), 0, 300),
        'source' => mb_substr((string) $request->input('source'), 0, 200),
        'trace' => mb_substr((string) $request->input('trace'), 0, 1500),
    ]);

    return response()->noContent();
})->middleware(['auth', 'throttle:20,1'])->name('studio.log');

/*
|--------------------------------------------------------------------------
| Commun aux comptes connectés
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.banned'])->group(function () {
    /*
     * Vérification du numéro de téléphone.
     *
     * Débitée sévèrement : chaque envoi coûte de l'argent, et sans cette
     * limite un bouton « renvoyer » permettrait de faire pleuvoir des SMS —
     * à nos frais — sur un numéro qu'on ne possède pas. Le service pose déjà
     * un délai par compte ; celle-ci borne la route elle-même.
     */
    Route::get('/telephone', [PhoneVerificationController::class, 'edit'])->name('phone.verify');

    Route::post('/telephone', [PhoneVerificationController::class, 'send'])
        ->middleware('throttle:5,10')
        ->name('phone.send');

    Route::post('/telephone/confirmer', [PhoneVerificationController::class, 'confirm'])
        ->middleware('throttle:10,10')
        ->name('phone.confirm');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Débité : un envoi de fichier coûte plus qu'une simple écriture.
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])
        ->middleware('throttle:10,1')
        ->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/lue', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/tout-lire', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Le fil avec l'équipe. L'équipe, elle, répond depuis le back-office :
    // elle n'a pas de fil à elle.
    Route::get('/messages', function () {
        if (auth()->user()->isStaff()) {
            return redirect('/admin/messagerie');
        }

        return view('messages.index');
    })->name('messages.index');
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

// Celle-ci est en français : elle est lue par des visiteurs français, et la
// loi qui l'impose est française.
Route::view('/mentions-legales', 'legal.notice', [
    'updatedAt' => '12 octobre 2026',
    'contactEmail' => config('legal.publisher.email') ?: config('mail.from.address'),
])->name('legal.notice');

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

        Route::get('/classement', [LeaderboardController::class, 'index'])->name('leaderboard.index');
        Route::get('/succes', [AchievementController::class, 'index'])->name('achievements.index');

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
    // Un créateur finance des campagnes : savoir le joindre compte autant que
    // pour un clippeur, et davantage le jour où un encaissement est contesté.
    ['phone.verified'],
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
