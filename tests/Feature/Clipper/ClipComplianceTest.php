<?php

namespace Tests\Feature\Clipper;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clips\ClipComplianceChecker;
use App\Support\Social\PostMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClipComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected ClipComplianceChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = app(ClipComplianceChecker::class);
    }

    protected function campaign(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(array_merge([
                'status' => CampaignStatus::Active,
                'required_hashtags' => ['#nayra', '#nouveausingle'],
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonth(),
            ], $attributes));
    }

    protected function clip(Campaign $campaign, ?SocialAccount $account = null): Clip
    {
        return Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'social_account_id' => $account?->getKey(),
            'user_id' => $account?->user_id ?? User::factory(),
            'platform' => Platform::TikTok,
        ]);
    }

    protected function metrics(array $overrides = []): PostMetrics
    {
        return new PostMetrics(...array_merge([
            'externalPostId' => 'post-1',
            'views' => 10_000,
            'caption' => 'Nouveau son 🔥 #nayra #nouveausingle',
            'durationSeconds' => 22,
            'postedAt' => now()->subDays(2),
            'ownerExternalId' => null,
        ], $overrides));
    }

    #[Test]
    public function a_compliant_clip_passes_every_check(): void
    {
        $clip = $this->checker->check($this->clip($this->campaign()), $this->metrics());

        $this->assertSame(ClipComplianceChecker::PASSED, $clip->compliance_status);
        $this->assertNotEmpty($clip->compliance['checks']);
        $this->assertTrue(collect($clip->compliance['checks'])->every(fn ($c) => $c['passed']));
    }

    #[Test]
    public function a_missing_hashtag_is_reported_by_name(): void
    {
        // Dire lequel manque évite un aller-retour avec le support.
        $clip = $this->checker->check(
            $this->clip($this->campaign()),
            $this->metrics(['caption' => 'Nouveau son 🔥 #nayra']),
        );

        $this->assertSame(ClipComplianceChecker::FAILED, $clip->compliance_status);

        $check = collect($clip->compliance['checks'])->firstWhere('label', 'Hashtags obligatoires présents');
        $this->assertFalse($check['passed']);
        $this->assertStringContainsString('#nouveausingle', $check['detail']);
    }

    #[Test]
    public function hashtags_are_matched_regardless_of_case(): void
    {
        $clip = $this->checker->check(
            $this->clip($this->campaign()),
            $this->metrics(['caption' => 'NOUVEAU SON #NAYRA #NouveauSingle']),
        );

        $this->assertSame(ClipComplianceChecker::PASSED, $clip->compliance_status);
    }

    #[Test]
    public function a_clip_that_is_too_short_fails(): void
    {
        $clip = $this->checker->check(
            $this->clip($this->campaign()),
            $this->metrics(['durationSeconds' => 4]),
        );

        $this->assertSame(ClipComplianceChecker::FAILED, $clip->compliance_status);
        $this->assertSame(4, $clip->duration_seconds);
    }

    #[Test]
    public function a_clip_published_before_the_campaign_fails(): void
    {
        $campaign = $this->campaign(['starts_at' => now()->subDays(3)]);

        $clip = $this->checker->check(
            $this->clip($campaign),
            $this->metrics(['postedAt' => now()->subDays(10)]),
        );

        $check = collect($clip->compliance['checks'])->firstWhere('label', 'Publié pendant la campagne');
        $this->assertFalse($check['passed']);
        $this->assertStringContainsString('avant le début', $check['detail']);
    }

    #[Test]
    public function a_post_owned_by_another_account_fails(): void
    {
        // Le contrôle qui empêche de soumettre la vidéo de quelqu'un d'autre.
        $account = SocialAccount::factory()->create(['external_account_id' => 'compte-du-clippeur']);

        $clip = $this->checker->check(
            $this->clip($this->campaign(), $account),
            $this->metrics(['ownerExternalId' => 'un-autre-compte']),
        );

        $check = collect($clip->compliance['checks'])->firstWhere('label', 'Publication émise par le compte lié');
        $this->assertFalse($check['passed']);
    }

    #[Test]
    public function the_report_never_decides_it_only_describes(): void
    {
        // Un clip non conforme reste en attente de modération : c'est un
        // modérateur qui refuse, pas le contrôle automatique.
        $clip = $this->clip($this->campaign());
        $statusBefore = $clip->status;

        $checked = $this->checker->check($clip, $this->metrics(['caption' => 'sans hashtag']));

        $this->assertSame(ClipComplianceChecker::FAILED, $checked->compliance_status);
        $this->assertSame($statusBefore, $checked->status);
    }

    #[Test]
    public function a_campaign_without_required_hashtags_skips_that_check(): void
    {
        $clip = $this->checker->check(
            $this->clip($this->campaign(['required_hashtags' => null])),
            $this->metrics(['caption' => 'aucune contrainte']),
        );

        $this->assertSame(ClipComplianceChecker::PASSED, $clip->compliance_status);
        $this->assertNull(
            collect($clip->compliance['checks'])->firstWhere('label', 'Hashtags obligatoires présents'),
        );
    }
}
