<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Creator;
use App\Models\User;
use App\Services\Social\SocialProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le site en mode production, tel qu'il tourne vraiment.
 *
 * Toute la suite tourne en environnement de test, où les fournisseurs
 * simulés prennent le relais de ce qui n'est pas configuré. La production,
 * elle, refuse ce relais — et c'est là que le site cassait sans qu'aucun test
 * ne le voie : la moitié des pages clippeur demandent l'état de chaque
 * plateforme, et YouTube et Instagram n'ont pas de clés. Le passage en
 * production aurait rendu des erreurs 500 sur « Mes comptes », « Mes clips »,
 * le catalogue et la page téléphone.
 *
 * Ces tests rejouent donc la configuration réelle : production, TikTok seul
 * configuré, aucun prestataire SMS.
 */
class ProductionModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->detectEnvironment(fn () => 'production');

        config([
            'services.tiktok.client_key' => 'cle-tiktok',
            'services.tiktok.client_secret' => 'secret-tiktok',
            'services.youtube.client_id' => null,
            'services.youtube.client_secret' => null,
            'services.instagram.app_id' => null,
            'services.instagram.app_secret' => null,
            'services.twilio.account_sid' => null,
        ]);
    }

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya.clips',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ]);
    }

    #[Test]
    public function every_clipper_page_answers(): void
    {
        $this->actingAs($this->clipper());

        foreach ([
            'dashboard', 'campaigns.index', 'clips.index', 'accounts.index',
            'earnings.index', 'leaderboard.index', 'achievements.index',
            'profile.edit', 'phone.verify', 'payout-method.edit',
        ] as $route) {
            $this->get(route($route))->assertSuccessful();
        }
    }

    #[Test]
    public function every_creator_page_answers(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        Creator::factory()->create(['user_id' => $user->getKey(), 'is_active' => true]);

        $this->actingAs($user);

        foreach (['creator.dashboard', 'creator.profile.edit', 'profile.edit'] as $route) {
            $this->get(route($route))->assertSuccessful();
        }
    }

    #[Test]
    public function only_the_configured_platform_is_offered(): void
    {
        // Une plateforme sans clés n'apparaît pas : un bouton qui ne mène
        // nulle part vaut moins que pas de bouton.
        $this->actingAs($this->clipper())
            ->get(route('accounts.index'))
            ->assertSuccessful()
            ->assertSee(route('social.redirect', 'tiktok', absolute: false), escape: false)
            ->assertDontSee(route('social.redirect', 'youtube', absolute: false), escape: false);
    }

    #[Test]
    public function asking_about_an_unconfigured_platform_never_throws(): void
    {
        $providers = app(SocialProviderManager::class);

        $this->assertFalse($providers->isSimulated(Platform::TikTok));
        $this->assertTrue($providers->isSimulated(Platform::YouTube));

        $this->assertTrue($providers->isAvailable(Platform::TikTok));
        $this->assertFalse($providers->isAvailable(Platform::YouTube));
    }

    #[Test]
    public function the_view_sync_runs_without_failing(): void
    {
        /*
         * Le relevé des vues est ce qui paie les clippeurs. Il parcourait
         * toutes les plateformes et levait sur la première sans clés, à
         * chaque passage. Il ne doit traiter que TikTok, et finir proprement.
         */
        $this->artisan('clips:sync')->assertSuccessful();
    }
}
