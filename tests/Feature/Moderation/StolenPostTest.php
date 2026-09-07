<?php

namespace Tests\Feature\Moderation;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clips\ClipComplianceChecker;
use App\Services\Social\ClipSyncService;
use App\Support\Social\PostMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La publication appartient à quelqu'un d'autre.
 *
 * C'est le seul contrôle de conformité qui bloque un paiement sans attendre un
 * humain. Les autres relèvent du jugement — un hashtag oublié se discute —
 * celui-ci est factuel et binaire, et se tromper signifie payer un clippeur
 * pour la vidéo virale d'un inconnu.
 *
 * La faille existait vraiment : le contrôle tournait, puis le crédit partait
 * deux lignes plus bas sans jamais le consulter.
 */
class StolenPostTest extends TestCase
{
    use RefreshDatabase;

    protected function approvedClip(): Clip
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);

        $clipper = User::factory()->create(['role' => UserRole::Clipper]);

        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'external_account_id' => 'compte-du-clippeur',
        ]);

        // Déjà validé par un modérateur avant le premier relevé : c'est
        // exactement la fenêtre dans laquelle la faille vivait.
        return Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'compliance_status' => null,
            'views_total' => 0,
            'posted_at' => now()->subDay(),
        ]);
    }

    /** Rejoue un relevé en imposant le propriétaire annoncé par la plateforme. */
    protected function syncWithOwner(Clip $clip, string $owner, int $views = 200_000): void
    {
        $service = app(ClipSyncService::class);
        $method = new \ReflectionMethod($service, 'applyMetrics');

        $method->invoke(
            $service,
            collect([$clip]),
            collect([$clip->external_post_id => new PostMetrics(
                externalPostId: $clip->external_post_id,
                views: $views,
                caption: 'Une légende',
                durationSeconds: 22,
                postedAt: $clip->posted_at,
                ownerExternalId: $owner,
            )]),
        );

        $clip->refresh();
    }

    #[Test]
    public function a_post_from_another_account_is_never_credited(): void
    {
        $clip = $this->approvedClip();

        $this->syncWithOwner($clip, 'compte-de-quelqu-un-dautre');

        $this->assertSame(0, $clip->earned_cents, 'Aucun euro ne doit partir sur une publication volée.');
        $this->assertSame(0, $clip->paid_views);
        $this->assertSame(0, $clip->campaign->fresh()->spent_cents);
    }

    #[Test]
    public function the_clippers_own_post_is_credited_normally(): void
    {
        $clip = $this->approvedClip();

        $this->syncWithOwner($clip, 'compte-du-clippeur');

        $this->assertSame(20_000, $clip->earned_cents);
    }

    #[Test]
    public function the_moderation_queue_is_told_once(): void
    {
        $clip = $this->approvedClip();

        // Trois relevés successifs : le clip reste volé, mais une seule ligne
        // doit apparaître — le relevé repasse toutes les trois heures.
        $this->syncWithOwner($clip, 'quelqu-un-dautre');
        $this->syncWithOwner($clip, 'quelqu-un-dautre');
        $this->syncWithOwner($clip, 'quelqu-un-dautre');

        $this->assertSame(1, ModerationLog::where('action', ModerationAction::ClipNotOwned)->count());
    }

    #[Test]
    public function the_views_are_still_recorded_for_the_moderator_to_see(): void
    {
        // On refuse de payer, on ne se prive pas de l'information : le
        // modérateur doit voir l'ampleur de ce qui a failli être versé.
        $clip = $this->approvedClip();

        $this->syncWithOwner($clip, 'quelqu-un-dautre', views: 500_000);

        $this->assertSame(500_000, $clip->views_total);
        $this->assertSame(ClipComplianceChecker::FAILED, $clip->compliance_status);
    }

    #[Test]
    public function a_missing_hashtag_does_not_block_the_payment(): void
    {
        // Contraste volontaire : les contrôles de jugement laissent le crédit
        // suivre son cours, quitte à être repris par une invalidation. Bloquer
        // sur un hashtag priverait de leur argent des clippeurs de bonne foi.
        $clip = $this->approvedClip();
        $clip->campaign->forceFill(['required_hashtags' => ['#absent']])->save();

        $this->syncWithOwner($clip->fresh(), 'compte-du-clippeur');

        $this->assertSame(ClipComplianceChecker::FAILED, $clip->fresh()->compliance_status);
        $this->assertGreaterThan(0, $clip->fresh()->earned_cents);
    }
}
