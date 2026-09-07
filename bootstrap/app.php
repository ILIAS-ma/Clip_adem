<?php

use App\Http\Middleware\EnsureAccountIsNotBanned;
use App\Http\Middleware\EnsureCreatorProfileExists;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Les proxys de confiance sont réglés dans AppServiceProvider, pas
        // ici : ce fichier s'exécute AVANT le chargement du .env, et `env()`
        // y renvoie toujours null. Le réglage n'a donc jamais pris effet tant
        // qu'il vivait à cet endroit.

        // Sur toute réponse, y compris les pages d'erreur : un en-tête de
        // sécurité qui ne s'applique qu'aux pages qui fonctionnent ne protège
        // pas grand-chose.
        $middleware->append(SecurityHeaders::class);

        // PayPal ne peut pas porter de jeton CSRF : la requête est authentifiée
        // par sa signature, vérifiée dans PayPalWebhookController.
        $middleware->validateCsrfTokens(except: [
            'webhooks/paypal',
        ]);

        // La connexion vit à la racine : un visiteur non authentifié y est
        // renvoyé, et un utilisateur déjà connecté repart vers son espace
        // plutôt que vers un `/dashboard` qui ne concerne que les clippeurs.
        $middleware->redirectTo(
            guests: '/',
            users: fn ($request) => $request->user()->role->homeRoute(),
        );

        $middleware->alias([
            'role' => EnsureRole::class,
            'profile.completed' => EnsureProfileIsComplete::class,
            'creator.profile' => EnsureCreatorProfileExists::class,
            'not.banned' => EnsureAccountIsNotBanned::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
