<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Réseaux sociaux. Tant qu'une plateforme n'a pas ses identifiants, un
    | fournisseur simulé prend le relais hors production — voir
    | SocialProviderManager.
    */
    /*
    | Connexion Google. Console Google Cloud > Identifiants > ID client OAuth,
    | type « Application Web ». L'URI de redirection autorisée doit être
    | exactement https://votre-domaine/auth/google/callback
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],

    'youtube' => [
        'client_id' => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
        'daily_quota' => env('YOUTUBE_DAILY_QUOTA', 10_000),

        // Vide : l'URL est déduite de la route. À figer dès qu'un tunnel ou un
        // répartiteur de charge s'intercale — voir ResolvesRedirectUri.
        'redirect' => env('YOUTUBE_REDIRECT_URI'),
    ],

    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'daily_quota' => env('TIKTOK_DAILY_QUOTA'),

        // Doit correspondre exactement aux portées activées dans la console —
        // un Sandbox a la sienne, souvent plus courte. En demander une que
        // l'application n'a pas fait échouer l'écran de consentement.
        'scopes' => env('TIKTOK_SCOPES', 'user.info.basic,video.list'),
        'redirect' => env('TIKTOK_REDIRECT_URI'),

        // Jeton de la méthode « balise meta » de vérification de domaine.
        'site_verification' => env('TIKTOK_SITE_VERIFICATION'),
    ],

    'instagram' => [
        'app_id' => env('INSTAGRAM_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'redirect' => env('INSTAGRAM_REDIRECT_URI'),
    ],

    /*
    | Cloudflare Turnstile — captcha de l'inscription publique. Les clés de
    | test ci-dessous (voir .env.example) valident toujours en local ; à
    | remplacer par les vraies clés du compte Cloudflare en production.
    */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    /*
    | PayPal Payouts. Les identifiants restent en .env : les stocker en base
    | reviendrait à mettre la trésorerie derrière un accès SQL.
    */
    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox | live
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'email_subject' => env('PAYPAL_EMAIL_SUBJECT', 'Votre rémunération Clip Adem'),
    ],

];
