<?php

namespace App\Providers;

use App\Contracts\CampaignBudgetService;
use App\Services\Budget\DatabaseCampaignBudgetService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Notifications\Messages\MailMessage;
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
        $this->trustConfiguredProxies();

        // Un mass assignment silencieux sur un modèle de budget passerait
        // inaperçu jusqu'au jour où il fausserait un paiement.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->localiseAuthEmails();
    }

    /**
     * Les e-mails d'authentification de Laravel sont en anglais par défaut.
     * Un clippeur francophone qui reçoit « Verify Email Address » le prend pour
     * du spam — c'est la première cause de comptes jamais activés.
     */
    protected function localiseAuthEmails(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Confirmez votre adresse — '.config('app.name'))
                ->greeting('Bienvenue !')
                ->line('Il ne reste qu\'une étape avant de pouvoir rejoindre vos premières campagnes : confirmer cette adresse e-mail.')
                ->action('Confirmer mon adresse', $url)
                ->line('Ce lien expire dans 60 minutes.')
                ->line('Si vous n\'êtes pas à l\'origine de cette inscription, ignorez simplement ce message.')
                ->salutation('À très vite, l\'équipe '.config('app.name'));
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Réinitialisation de votre mot de passe — '.config('app.name'))
                ->greeting('Bonjour,')
                ->line('Vous avez demandé à réinitialiser votre mot de passe.')
                ->action('Choisir un nouveau mot de passe', url($url))
                ->line('Ce lien expire dans '.config('auth.passwords.users.expire').' minutes.')
                ->line('Si vous n\'avez rien demandé, aucune action n\'est nécessaire.')
                ->salutation('L\'équipe '.config('app.name'));
        });
    }

    /**
     * Accorde la confiance aux proxys déclarés en configuration.
     *
     * Réglé ici et pas dans `bootstrap/app.php` : ce fichier-là s'exécute avant
     * le chargement du `.env`, où `env()` renvoie systématiquement null. Le
     * réglage y était donc silencieusement sans effet — Laravel continuait de
     * fabriquer des URL `http://` derrière un tunnel HTTPS, et le navigateur
     * bloquait les feuilles de style de la page.
     *
     * Les fournisseurs de services démarrent avant que la moindre requête ne
     * traverse la pile de middlewares : le réglage est en place à temps.
     */
    protected function trustConfiguredProxies(): void
    {
        $proxies = config('app.trusted_proxies');

        if (blank($proxies)) {
            return;
        }

        TrustProxies::at($proxies === '*' ? '*' : array_map(trim(...), explode(',', (string) $proxies)));
    }
}
