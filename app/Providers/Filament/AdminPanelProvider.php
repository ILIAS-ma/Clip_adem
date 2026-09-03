<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\BudgetConsumptionChart;
use App\Filament\Widgets\PlatformOverview;
use App\Filament\Widgets\SpendPerArtist;
use App\Filament\Widgets\TopClippers;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Clip Adem')
            ->login()
            ->profile(isSimple: false)
            /*
             * 2FA TOTP obligatoire pour tout le personnel : un compte admin
             * compromis donne accès aux paiements. Un administrateur qui ne l'a
             * pas encore configurée est redirigé vers l'écran de mise en place
             * avant de pouvoir faire quoi que ce soit d'autre.
             */
            ->multiFactorAuthentication(
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('Clip Adem'),
                // Suspendable depuis config/clipping.php pour parcourir le
                // back-office sans application TOTP sous la main. Le dispositif
                // reste installé et utilisable — un administrateur peut
                // l'activer depuis son profil.
                isRequired: (bool) config('clipping.onboarding.require_admin_2fa'),
            )
            // Même identité que le front : lime sur noir. Le back-office force
            // le mode sombre plutôt que de suivre le réglage du système, sinon
            // l'admin et le site public n'auraient pas l'air du même produit.
            ->colors([
                'primary' => Color::hex('#93CE2E'),
            ])
            ->defaultThemeMode(ThemeMode::Dark)
            ->brandLogo(fn () => is_file(public_path('images/logo.png'))
                ? view('filament.brand-logo')
                : null)
            ->brandLogoHeight('2rem')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            /*
             * Enregistrement explicite plutôt qu'auto-découverte :
             * CampaignBudgetOverview attend une campagne et n'a rien à faire
             * sur le tableau de bord, où elle s'afficherait vide.
             */
            ->widgets([
                PlatformOverview::class,
                BudgetConsumptionChart::class,
                SpendPerArtist::class,
                TopClippers::class,
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
