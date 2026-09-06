<?php

namespace Tests\Feature\Console;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le contrôle avant ouverture.
 *
 * Une commande de vérification en laquelle on n'a pas confiance est pire que
 * pas de commande : elle donne le sentiment d'avoir vérifié. D'où ces tests
 * sur ce qu'elle laisse passer autant que sur ce qu'elle arrête.
 */
class PreflightTest extends TestCase
{
    use RefreshDatabase;

    /** Une configuration de production complète et saine. */
    protected function productionConfig(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://clip.example',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),

            'clipping.onboarding.require_email_verification' => true,
            'clipping.onboarding.require_complete_profile' => true,
            'clipping.onboarding.require_admin_2fa' => true,
            'clipping.onboarding.require_creator_validation' => true,
            'clipping.onboarding.require_funded_campaigns' => true,

            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.postmarkapp.com',

            'services.tiktok.client_key' => 'k',
            'services.tiktok.client_secret' => 's',
            'services.youtube.client_id' => 'k',
            'services.youtube.client_secret' => 's',
            'services.instagram.app_id' => 'k',
            'services.instagram.app_secret' => 's',

            'services.paypal.mode' => 'live',
            'services.paypal.client_id' => 'k',
            'services.paypal.client_secret' => 's',
            'services.paypal.webhook_id' => 'w',
        ]);

        // `isProduction()` lit l'environnement de l'application, pas seulement
        // la configuration.
        $this->app->detectEnvironment(fn () => 'production');
    }

    #[Test]
    public function a_healthy_production_setup_passes(): void
    {
        $this->productionConfig();

        $this->artisan('clip:preflight')->assertSuccessful();
    }

    #[Test]
    public function a_suspended_gate_blocks_the_launch(): void
    {
        // Le scénario réel : un `.env` de développement recopié sur le serveur.
        $this->productionConfig();
        config(['clipping.onboarding.require_email_verification' => false]);

        $this->artisan('clip:preflight')
            ->expectsOutputToContain('Vérification d\'e-mail')
            ->assertFailed();
    }

    #[Test]
    public function debug_mode_left_on_blocks_the_launch(): void
    {
        $this->productionConfig();
        config(['app.debug' => true]);

        $this->artisan('clip:preflight')->assertFailed();
    }

    #[Test]
    public function emails_captured_locally_block_the_launch(): void
    {
        // Sans SMTP réel, aucune confirmation d'adresse ni avis de versement
        // ne sortirait — et personne ne s'en apercevrait avant les plaintes.
        $this->productionConfig();
        config(['mail.mailers.smtp.host' => '127.0.0.1']);

        $this->artisan('clip:preflight')->assertFailed();
    }

    #[Test]
    public function missing_platform_keys_block_the_launch(): void
    {
        // En production, l'absence de clés lève une exception au premier usage :
        // plus aucune liaison de compte, plus aucun relevé de vues.
        $this->productionConfig();
        config(['services.tiktok.client_key' => null]);

        $this->artisan('clip:preflight')->assertFailed();
    }

    #[Test]
    public function an_uncovered_campaign_warns_without_blocking(): void
    {
        // C'est une alerte de trésorerie, pas un défaut technique : elle ne
        // doit pas empêcher un déploiement.
        $this->productionConfig();

        Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
                'spent_cents' => 40_000,
            ]);

        $this->artisan('clip:preflight')
            ->expectsOutputToContain('découvert')
            ->assertSuccessful();
    }

    #[Test]
    public function demo_accounts_left_behind_block_the_launch(): void
    {
        // Mots de passe publics et connus de tous ceux qui ont lu le README.
        $this->productionConfig();

        User::factory()->create(['email' => 'admin@clip.test']);

        $this->artisan('clip:preflight')->assertFailed();
    }
}
