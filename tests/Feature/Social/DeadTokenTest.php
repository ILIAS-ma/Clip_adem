<?php

namespace Tests\Feature\Social;

use App\Contracts\SocialProvider;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\SocialProviderFailed;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\ClipSyncService;
use App\Services\Social\SocialProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jeton refusé par la plateforme.
 *
 * Sans ce marquage, un jeton mort est réinterrogé à chaque passage : il
 * consomme le quota qui manquera aux comptes valides, et le clippeur ne voit
 * jamais pourquoi ses vues ont cessé de monter. Le bandeau d'alerte de son
 * espace ne s'allume que sur cet indicateur.
 */
class DeadTokenTest extends TestCase
{
    use RefreshDatabase;

    protected Clip $clip;

    protected SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);

        $clipper = User::factory()->create(['role' => UserRole::Clipper]);

        $this->account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'needs_reconnect' => false,
        ]);

        $this->clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $this->account->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'posted_at' => now()->subDay(),
            'last_synced_at' => null,
        ]);
    }

    /** Fait répondre la plateforme par l'erreur voulue. */
    protected function providerFailsWith(SocialProviderFailed $exception): void
    {
        $provider = Mockery::mock(SocialProvider::class);
        $provider->shouldReceive('fetchPosts')->andThrow($exception);
        $provider->shouldReceive('platform')->andReturn(Platform::TikTok);
        $provider->shouldReceive('batchSize')->andReturn(20);
        $provider->shouldReceive('quotaCostPerCall')->andReturn(1);
        $provider->shouldReceive('dailyQuota')->andReturn(null);

        $manager = Mockery::mock(SocialProviderManager::class);
        $manager->shouldReceive('for')->andReturn($provider);

        $this->app->instance(SocialProviderManager::class, $manager);
    }

    #[Test]
    public function a_refused_token_marks_the_account_for_reconnection(): void
    {
        // C'est exactement ce que renvoie TikTok quand le jeton n'a pas la
        // portée demandée, ou qu'il n'est plus valide.
        $this->providerFailsWith(SocialProviderFailed::fetchFailed(
            Platform::TikTok,
            401,
            '{"error":{"code":"scope_not_authorized"}}',
        ));

        app(ClipSyncService::class)->syncClip($this->clip);

        $this->account->refresh();

        $this->assertTrue($this->account->needs_reconnect);
        $this->assertStringContainsString('scope_not_authorized', (string) $this->account->last_error);
    }

    #[Test]
    public function a_flagged_account_is_no_longer_queried(): void
    {
        // Le but du marquage : cesser de brûler du quota sur un compte mort.
        $this->account->forceFill(['needs_reconnect' => true])->save();

        $this->assertFalse(app(ClipSyncService::class)->syncClip($this->clip->fresh()));
    }

    #[Test]
    public function a_passing_outage_does_not_force_a_reconnection(): void
    {
        /*
         * Une panne de l'API n'est pas un problème d'autorisation. Obliger
         * quelqu'un à refaire son consentement OAuth parce que TikTok a
         * renvoyé un 500 pendant deux minutes lui ferait perdre ses vues du
         * jour pour rien.
         */
        $this->providerFailsWith(SocialProviderFailed::fetchFailed(Platform::TikTok, 500, 'oups'));

        app(ClipSyncService::class)->syncClip($this->clip);

        $this->assertFalse($this->account->fresh()->needs_reconnect);
    }

    #[Test]
    public function the_exception_carries_the_status_it_reports(): void
    {
        // Le code HTTP est porté par l'exception plutôt que noyé dans le
        // message : c'est lui qui décide si l'on réessaie ou si l'on arrête.
        $this->assertTrue(SocialProviderFailed::fetchFailed(Platform::TikTok, 401, '')->isAuthFailure());
        $this->assertTrue(SocialProviderFailed::fetchFailed(Platform::TikTok, 403, '')->isAuthFailure());
        $this->assertFalse(SocialProviderFailed::fetchFailed(Platform::TikTok, 429, '')->isAuthFailure());
        $this->assertFalse(SocialProviderFailed::fetchFailed(Platform::TikTok, 500, '')->isAuthFailure());
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
