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
use App\Support\Social\SyncOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Quand c'est l'application qui n'a pas la permission.
 *
 * Une portée jamais obtenue et un jeton mort se ressemblent : tous deux
 * renvoient 401. Mais le premier ne se répare pas en se reconnectant — le
 * consentement redonnerait exactement les mêmes droits.
 *
 * Confondus, ils produisaient deux mensonges à la fois : le compte était
 * marqué « à reconnecter », et le clippeur lisait que sa publication avait été
 * supprimée ou rendue privée. Sa vidéo était en ligne, ses vues bien réelles,
 * et il pouvait recommencer indéfiniment un geste qui ne pouvait pas aboutir.
 */
class MissingPermissionTest extends TestCase
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
            ->create(['status' => CampaignStatus::Active, 'budget_total_cents' => 100_000]);

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

    protected function providerFailsWith(SocialProviderFailed $exception): void
    {
        $provider = Mockery::mock(SocialProvider::class);
        $provider->shouldReceive('fetchPosts')->andThrow($exception);
        $provider->shouldReceive('platform')->andReturn(Platform::TikTok);

        $manager = Mockery::mock(SocialProviderManager::class);
        $manager->shouldReceive('for')->andReturn($provider);

        $this->app->instance(SocialProviderManager::class, $manager);
    }

    #[Test]
    public function a_missing_scope_does_not_ask_the_clipper_to_reconnect(): void
    {
        $this->providerFailsWith(SocialProviderFailed::fetchFailed(
            Platform::TikTok,
            401,
            '{"error":{"code":"scope_not_authorized","message":"The user did not authorize the scope"}}',
        ));

        app(ClipSyncService::class)->refreshClip($this->clip);

        $this->assertFalse(
            $this->account->fresh()->needs_reconnect,
            'Reconnecter ne change rien quand la portée manque à l’application.',
        );
    }

    #[Test]
    public function the_message_does_not_accuse_the_video(): void
    {
        $this->providerFailsWith(SocialProviderFailed::fetchFailed(
            Platform::TikTok,
            401,
            '{"error":{"code":"scope_not_authorized"}}',
        ));

        $outcome = app(ClipSyncService::class)->refreshClip($this->clip);

        $this->assertSame(SyncOutcome::MissingPermission, $outcome);

        $message = $outcome->message();

        $this->assertStringNotContainsStringIgnoringCase('supprimée', $message);
        $this->assertStringNotContainsStringIgnoringCase('reconnect', $message);
        $this->assertStringContainsStringIgnoringCase('vos vues existent bien', $message);
    }

    #[Test]
    public function a_genuinely_dead_token_still_asks_for_a_reconnection(): void
    {
        /*
         * L'assouplissement ne doit pas dispenser du marquage quand il est
         * justifié : un jeton expiré se répare bien en se reconnectant, et sans
         * ce signal le bandeau d'alerte ne s'allume jamais.
         */
        $this->providerFailsWith(SocialProviderFailed::fetchFailed(
            Platform::TikTok,
            401,
            '{"error":{"code":"access_token_invalid"}}',
        ));

        app(ClipSyncService::class)->refreshClip($this->clip);

        $this->assertTrue($this->account->fresh()->needs_reconnect);
    }

    #[Test]
    public function the_two_failures_are_told_apart_on_the_exception_itself(): void
    {
        $portee = SocialProviderFailed::fetchFailed(Platform::TikTok, 401, '{"error":{"code":"scope_not_authorized"}}');
        $jeton = SocialProviderFailed::fetchFailed(Platform::TikTok, 401, '{"error":{"code":"access_token_invalid"}}');

        $this->assertTrue($portee->isMissingPermission());
        $this->assertFalse($jeton->isMissingPermission());

        // Les deux restent des échecs d'autorisation : c'est ce qui arrête
        // d'interroger le compte plutôt que de réessayer en boucle.
        $this->assertTrue($portee->isAuthFailure());
        $this->assertTrue($jeton->isAuthFailure());
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
