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
use App\Services\Social\ClipSyncService;
use App\Support\Social\PostMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Publication effacée après avoir été payée.
 *
 * Le vecteur de fraude le moins coûteux contre la plateforme : publier,
 * encaisser sur trois jours, supprimer la vidéo. Avant, le relevé notait
 * simplement « rien à lire » et passait au suivant — personne n'était jamais
 * prévenu.
 */
class DisappearedClipTest extends TestCase
{
    use RefreshDatabase;

    protected function clip(int $earnedCents = 0): Clip
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
        ]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => 50_000,
        ]);

        if ($earnedCents > 0) {
            $clip->forceFill(['earned_cents' => $earnedCents, 'paid_views' => 50_000])->save();
        }

        return $clip->fresh();
    }

    #[Test]
    public function one_missed_reading_is_not_an_accusation(): void
    {
        // Une publication passée en privé une heure, une API qui bafouille :
        // accuser quelqu'un là-dessus coûte plus cher que d'attendre.
        $clip = $this->clip();

        $this->markAsMissing($clip, times: 1);

        $this->assertTrue($clip->isMissing());
        $this->assertFalse($clip->hasDisappeared());
        $this->assertSame(0, ModerationLog::where('action', ModerationAction::ClipDisappeared)->count());
    }

    #[Test]
    public function two_missed_readings_raise_the_flag_once(): void
    {
        $clip = $this->clip();

        $this->markAsMissing($clip, times: 4);

        $this->assertTrue($clip->hasDisappeared());

        // Une seule ligne, pas une par relevé : sinon la file de modération
        // devient illisible au bout d'une journée.
        $this->assertSame(1, ModerationLog::where('action', ModerationAction::ClipDisappeared)->count());
    }

    #[Test]
    public function a_publication_that_comes_back_clears_the_suspicion(): void
    {
        $clip = $this->clip();
        $this->markAsMissing($clip, times: 3);

        $this->assertTrue($clip->hasDisappeared());

        // Elle réapparaît : elle était privée, ou l'API a menti.
        app(ClipSyncService::class);
        $this->applyReturningPost($clip);

        $this->assertFalse($clip->isMissing());
        $this->assertFalse($clip->hasDisappeared());
    }

    #[Test]
    public function the_money_already_credited_is_never_taken_back_automatically(): void
    {
        // L'invariant du projet : seul un modérateur peut rendre le budget, par
        // une invalidation explicite. Une disparition signale, elle ne juge pas.
        $clip = $this->clip(earnedCents: 5_000);

        $this->markAsMissing($clip, times: 5);

        $this->assertSame(5_000, $clip->earned_cents);
        $this->assertSame(ClipStatus::Approved, $clip->status);
    }

    #[Test]
    public function a_disappearance_after_payment_says_how_much_is_at_stake(): void
    {
        $clip = $this->clip(earnedCents: 5_000);

        $this->markAsMissing($clip, times: 2);

        $this->assertTrue($clip->disappearedAfterBeingPaid());

        $log = ModerationLog::where('action', ModerationAction::ClipDisappeared)->sole();
        // Le modérateur doit voir l'enjeu sans ouvrir le clip.
        $this->assertStringContainsString('50,00 €', $log->reason);
    }

    #[Test]
    public function a_disappearance_before_any_payment_is_reported_without_an_amount(): void
    {
        $clip = $this->clip();

        $this->markAsMissing($clip, times: 2);

        $this->assertFalse($clip->disappearedAfterBeingPaid());

        $log = ModerationLog::where('action', ModerationAction::ClipDisappeared)->sole();
        $this->assertStringNotContainsString('€', $log->reason);
    }

    // ------------------------------------------------------------------

    /** Rejoue N relevés où la publication reste introuvable. */
    protected function markAsMissing(Clip $clip, int $times): void
    {
        $service = app(ClipSyncService::class);
        $method = new \ReflectionMethod($service, 'noteMissingPost');

        for ($i = 0; $i < $times; $i++) {
            $method->invoke($service, $clip);
            $clip->refresh();
        }
    }

    /** Rejoue un relevé où la publication est de nouveau là. */
    protected function applyReturningPost(Clip $clip): void
    {
        $service = app(ClipSyncService::class);
        $method = new \ReflectionMethod($service, 'applyMetrics');

        $method->invoke(
            $service,
            collect([$clip]),
            collect([$clip->external_post_id => new PostMetrics(
                externalPostId: $clip->external_post_id,
                views: $clip->views_total,
                caption: $clip->caption,
                durationSeconds: $clip->duration_seconds ?? 20,
                postedAt: $clip->posted_at ?? now()->subDay(),
                ownerExternalId: $clip->socialAccount?->external_account_id,
            )]),
        );

        $clip->refresh();
    }
}
