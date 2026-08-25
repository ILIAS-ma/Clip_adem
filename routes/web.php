<?php

use App\Http\Controllers\PayPalWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// Retours asynchrones de PayPal sur les versements. Hors session et hors CSRF :
// l'authenticité est établie par la signature de la requête (voir le contrôleur).
Route::post('/webhooks/paypal', PayPalWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paypal');
