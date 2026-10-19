<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AdminList;
use App\Filament\Widgets\BudgetConsumptionChart;
use App\Filament\Widgets\PlatformOverview;
use App\Filament\Widgets\SpendPerCreator;
use App\Filament\Widgets\TopClippers;
use App\Filament\Widgets\TopClippersWeekly;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(config('app.name'))
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
                    ->brandName(config('app.name')),
                // Suspendable depuis config/clipping.php pour parcourir le
                // back-office sans application TOTP sous la main. Le dispositif
                // reste installé et utilisable — un administrateur peut
                // l'activer depuis son profil.
                isRequired: (bool) config('clipping.onboarding.require_admin_2fa'),
            )
            // Même identité que le front : le rouge du logo sur du noir pur,
            // des gris neutres plutôt que les gris bleutés par défaut. Le
            // back-office force le mode sombre plutôt que de suivre le réglage
            // du système, sinon l'admin et le site public n'auraient pas l'air
            // du même produit.
            ->colors([
                'primary' => Color::hex('#CC3F45'),
                'gray' => Color::Neutral,
            ])
            ->favicon(asset('images/logo.png'))
            ->defaultThemeMode(ThemeMode::Dark)
            ->brandLogo(fn () => is_file(public_path('images/logo.png'))
                ? view('filament.brand-logo')
                : null)
            ->brandLogoHeight('2rem')
            // Même famille que le site public : un back-office qui n'a pas la
            // typographie du produit donne l'impression d'un outil emprunté.
            ->font('Figtree')
            /*
             * Groupes ordonnés du quotidien vers l'exceptionnel : on vient ici
             * modérer des clips bien plus souvent que créer une campagne, et
             * beaucoup plus souvent que payer.
             */
            ->navigationGroups([
                NavigationGroup::make('Catalogue')
                    ->icon(Heroicon::OutlinedRectangleStack),
                NavigationGroup::make('Modération')
                    ->icon(Heroicon::OutlinedShieldCheck),
                NavigationGroup::make('Finances')
                    ->icon(Heroicon::OutlinedBanknotes),
                NavigationGroup::make('Communication')
                    ->icon(Heroicon::OutlinedEnvelope),
            ])
            /*
             * Tout administrateur est aussi clippeur (User::canClip) : le
             * passage vers son espace clippeur est en tête du menu, et le
             * retour par « Administration » de l'autre côté.
             */
            ->navigationItems([
                NavigationItem::make('Espace clippeur')
                    ->url(fn () => route('dashboard'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->sort(-100),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            /*
             * Le formulaire de campagne est long et porte des téléversements :
             * le quitter par inadvertance coûte le brief entier et les fichiers
             * déjà déposés.
             */
            ->unsavedChangesAlerts()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // La connexion temps réel de la messagerie, dans la tête : elle
            // doit exister avant que Livewire n'abonne la boîte de réception.
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => Blade::render("@vite('resources/js/echo.js')"))
            /*
             * Enregistrement explicite plutôt qu'auto-découverte :
             * CampaignBudgetOverview attend une campagne et n'a rien à faire
             * sur le tableau de bord, où elle s'afficherait vide.
             */
            ->widgets([
                PlatformOverview::class,
                BudgetConsumptionChart::class,
                SpendPerCreator::class,
                TopClippers::class,
                TopClippersWeekly::class,
                AdminList::class,
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
