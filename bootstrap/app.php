<?php

use App\Http\Middleware\EnsureAccountIsNotBanned;
use App\Http\Middleware\EnsureCreatorProfileExists;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureRole;
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
