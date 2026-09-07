<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Confiance accordée aux proxys.
 *
 * Derrière un tunnel ou un répartiteur de charge, TLS est terminé en amont.
 * Sans ce réglage, Laravel se croit en HTTP et fabrique des URL `http://` : le
 * navigateur bloque alors les feuilles de style d'une page HTTPS — le site
 * s'affiche sans aucun style — et l'adresse de retour OAuth ne correspond plus
 * à celle enregistrée chez le fournisseur.
 *
 * Le réglage a vécu un temps dans `bootstrap/app.php`, où il n'a jamais rien
 * fait : ce fichier s'exécute avant le chargement du `.env`, et `env()` y
 * renvoie null. Ces tests existent pour que la panne ne puisse pas revenir en
 * silence.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_scheme-probe', fn () => response()->json([
            'secure' => request()->isSecure(),
            'url' => url('/quelque-chose'),
        ]))->middleware('web');
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    #[Test]
    public function a_forwarded_https_request_is_seen_as_secure(): void
    {
        TrustProxies::at('*');

        $this->get('/_scheme-probe', ['X-Forwarded-Proto' => 'https'])
            ->assertSuccessful()
            ->assertJson(['secure' => true]);
    }

    #[Test]
    public function generated_urls_follow_the_forwarded_scheme(): void
    {
        // C'est ce qui casse visiblement : une feuille de style en `http` sur
        // une page `https` est bloquée par le navigateur.
        TrustProxies::at('*');

        $response = $this->get('/_scheme-probe', ['X-Forwarded-Proto' => 'https']);

        $this->assertStringStartsWith('https://', $response->json('url'));
    }

    #[Test]
    public function nothing_is_trusted_when_the_setting_is_empty(): void
    {
        /*
         * Le défaut doit rester méfiant : une application joignable en direct
         * ne doit pas laisser n'importe qui décréter le schéma de la requête —
         * ni son adresse IP, ce qui contournerait les limitations de débit.
         *
         * On éprouve le garde-fou du fournisseur de services lui-même plutôt
         * qu'une requête HTTP : le `.env` de la machine qui lance les tests
         * déclare peut-être un proxy, et le test dirait alors n'importe quoi.
         */
        TrustProxies::flushState();
        config(['app.trusted_proxies' => null]);

        $this->bootProxies();

        $this->assertNull($this->trustedProxiesSetting());
    }

    #[Test]
    public function a_comma_separated_list_becomes_an_array_of_addresses(): void
    {
        // Un répartiteur de charge en déclare souvent plusieurs, et les espaces
        // autour des virgules sont la faute de frappe classique.
        TrustProxies::flushState();
        config(['app.trusted_proxies' => '10.0.0.1, 10.0.0.2']);

        $this->bootProxies();

        $this->assertSame(['10.0.0.1', '10.0.0.2'], $this->trustedProxiesSetting());
    }

    /** Rejoue le réglage du fournisseur de services. */
    protected function bootProxies(): void
    {
        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'trustConfiguredProxies');
        $method->invoke($provider);
    }

    /** Lit la propriété statique posée par TrustProxies::at(). */
    protected function trustedProxiesSetting(): mixed
    {
        $property = new \ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies');

        return $property->getValue();
    }

    #[Test]
    public function the_setting_is_read_from_configuration_not_from_the_bootstrap(): void
    {
        /*
         * La clé de la panne : `bootstrap/app.php` s'exécute avant le
         * chargement du `.env`. Le réglage doit donc passer par la
         * configuration, lue après. Ce test échouerait si quelqu'un remettait
         * `env('TRUSTED_PROXIES')` dans le bootstrap et retirait la clé de
         * configuration.
         */
        $this->assertArrayHasKey('trusted_proxies', config('app'));

        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringNotContainsString(
            "env('TRUSTED_PROXIES')",
            $bootstrap,
            'Lire cette variable dans bootstrap/app.php ne fonctionne pas : le .env n’y est pas encore chargé.',
        );
    }
}
