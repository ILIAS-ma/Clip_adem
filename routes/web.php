<?php

use App\Http\Controllers\Clipper\CampaignController;
use App\Http\Controllers\Clipper\DashboardController;
use App\Http\Controllers\Clipper\ProfileCompletionController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('home');

// Retours asynchrones de PayPal sur les versements. Hors session et hors CSRF :
// l'authenticité est établie par la signature de la requête (voir le contrôleur).
Route::post('/webhooks/paypal', PayPalWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paypal');

/*
|--------------------------------------------------------------------------
| Espace clippeur
|--------------------------------------------------------------------------
|
| `not.banned` déconnecte un compte suspendu plutôt que de le laisser naviguer
| avec une session déjà ouverte ; `staff.redirect` renvoie les administrateurs
| vers leur panel au lieu de leur montrer un espace qui ne les concerne pas.
|
*/
Route::middleware(['auth', 'not.banned', 'staff.redirect'])->group(function () {

    // Hors du groupe « profil complet », sinon la redirection boucle sur elle-même.
    Route::get('/profil/completer', [ProfileCompletionController::class, 'edit'])->name('profile.complete');
    Route::patch('/profil/completer', [ProfileCompletionController::class, 'update'])->name('profile.complete.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware(['verified', 'profile.completed'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/campagnes', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campagnes/{campaign:slug}', [CampaignController::class, 'show'])->name('campaigns.show');
    });
});

require __DIR__.'/auth.php';
