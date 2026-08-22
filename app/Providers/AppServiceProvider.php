<?php

namespace App\Providers;

use App\Contracts\CampaignBudgetService;
use App\Services\Budget\DatabaseCampaignBudgetService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Le module clippeur type-hint l'interface, jamais l'implémentation :
        // le moteur reste remplaçable et mockable dans ses propres tests.
        $this->app->singleton(CampaignBudgetService::class, DatabaseCampaignBudgetService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Un mass assignment silencieux sur un modèle de budget passerait
        // inaperçu jusqu'au jour où il fausserait un paiement.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
