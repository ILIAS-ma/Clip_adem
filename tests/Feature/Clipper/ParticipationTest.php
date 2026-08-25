<?php

namespace Tests\Feature\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ParticipationStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\ClipSubmissionRefused;
use App\Exceptions\ParticipationRefused;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clips\ClipSubmissionService;
use App\Services\Clips\ParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParticipationTest extends TestCase
{
    use RefreshDatabase;

    protected ParticipationService $participations;

    protected ClipSubmissionService $submissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->participations = app(ParticipationService::class);
        $this->submissions = app(ClipSubmissionService::class);
    }

    protected function campaign(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(array_merge([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
                'requires_approval' => false,
            ], $attributes));
    }

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
        ]);
    }

    protected function account(User $user, Platform $platform = Platform::TikTok): SocialAccount
    {
        return SocialAccount::factory()->create([
            'user_id' => $user->getKey(),
            'platform' => $platform,
        ]);
    }

    #[Test]
    public function joining_an_open_campaign_approves_immediately_when_no_review_is_required(): void
    {
        $clipper = $this->clipper();
        $participation = $this->participations->join(
            $this->campaign(),
            $clipper,
            $this->account($clipper),
        );

        $this->assertSame(ParticipationStatus::Approved, $participation->status);
        $this->assertNotNull($participation->approved_at);
    }

    #[Test]
    public function a_campaign_requiring_review_leaves_the_participation_pending(): void
    {
        $clipper = $this->clipper();
        $participation = $this->participations->join(
            $this->campaign(['requires_approval' => true]),
            $clipper,
            $this->account($clipper),
        );

        $this->assertSame(ParticipationStatus::Pending, $participation->status);
        $this->assertNull($participation->approved_at);
    }

    #[Test]
    public function an_exhausted_campaign_refuses_new_participants(): void
    {
        $campaign = $this->campaign();
        $campaign->forceFill([
            'spent_cents' => 100_000,
            'status' => CampaignStatus::Exhausted,
        ])->save();

        $clipper = $this->clipper();

        $this->expectException(ParticipationRefused::class);
        $this->expectExceptionMessage('épuisé');

        $this->participations->join($campaign, $clipper, $this->account($clipper));
    }

    #[Test]
    public function the_same_account_cannot_join_twice(): void
    {
        $campaign = $this->campaign();
        $clipper = $this->clipper();
        $account = $this->account($clipper);

        $this->participations->join($campaign, $clipper, $account);

        $this->expectException(ParticipationRefused::class);
        $this->expectExceptionMessage('participe déjà');

        $this->participations->join($campaign, $clipper, $account);
    }

    #[Test]
    public function a_platform_without_a_rate_is_refused(): void
    {
        $clipper = $this->clipper();

        $this->expectException(ParticipationRefused::class);
        $this->expectExceptionMessage('YouTube');

        $this->participations->join(
            $this->campaign(),
            $clipper,
            $this->account($clipper, Platform::YouTube),
        );
    }

    #[Test]
    public function an_account_needing_reconnection_is_refused(): void
    {
        // Laisser rejoindre avec un jeton mort produirait des clips dont les
        // vues ne seront jamais comptées : mieux vaut refuser tout de suite.
        $clipper = $this->clipper();
        $account = $this->account($clipper);
        $account->forceFill(['needs_reconnect' => true])->save();

        $this->expectException(ParticipationRefused::class);
        $this->expectExceptionMessage('Reconnectez');

        $this->participations->join($this->campaign(), $clipper, $account->fresh());
    }

    #[Test]
    public function a_clipper_cannot_use_someone_elses_account(): void
    {
        $clipper = $this->clipper();
        $other = $this->clipper();

        $this->expectException(ParticipationRefused::class);
        $this->expectExceptionMessage("n'est pas rattaché");

        $this->participations->join($this->campaign(), $clipper, $this->account($other));
    }

    #[Test]
    public function eligible_accounts_exclude_those_already_engaged(): void
    {
        $campaign = $this->campaign();
        $clipper = $this->clipper();
        $first = $this->account($clipper);
        $this->account($clipper, Platform::YouTube); // pas de taux : inéligible

        $this->assertCount(1, $this->participations->eligibleAccounts($campaign, $clipper));

        $this->participations->join($campaign, $clipper, $first);

        $this->assertCount(0, $this->participations->eligibleAccounts($campaign, $clipper));
    }

    // ------------------------------------------------------------------
    // Soumission de clip
    // ------------------------------------------------------------------

    #[Test]
    public function submitting_a_clip_creates_it_pending_review(): void
    {
        $campaign = $this->campaign();
        $clipper = $this->clipper();
        $this->participations->join($campaign, $clipper, $this->account($clipper));

        $clip = $this->submissions->submit(
            $campaign,
            $clipper,
            'https://www.tiktok.com/@lina.clips/video/7123456789012345678?is_from_webapp=1',
        );

        $this->assertSame(ClipStatus::PendingReview, $clip->status);
        $this->assertSame('7123456789012345678', $clip->external_post_id);
        // L'URL est normalisée : les paramètres de suivi disparaissent.
        $this->assertSame('https://www.tiktok.com/@lina.clips/video/7123456789012345678', $clip->url);
        $this->assertSame(0, $clip->earned_cents);
        $this->assertNotNull($clip->submitted_at);
    }

    #[Test]
    public function submitting_without_joining_is_refused(): void
    {
        $this->expectException(ClipSubmissionRefused::class);
        $this->expectExceptionMessage('Rejoignez la campagne');

        $this->submissions->submit(
            $this->campaign(),
            $this->clipper(),
            'https://www.tiktok.com/@lina.clips/video/7123456789012345678',
        );
    }

    #[Test]
    public function a_pending_participation_cannot_submit_yet(): void
    {
        $campaign = $this->campaign(['requires_approval' => true]);
        $clipper = $this->clipper();
        $this->participations->join($campaign, $clipper, $this->account($clipper));

        $this->expectException(ClipSubmissionRefused::class);
        $this->expectExceptionMessage('validation');

        $this->submissions->submit($campaign, $clipper, 'https://www.tiktok.com/@l/video/7123456789012345678');
    }

    #[Test]
    public function a_link_from_another_platform_than_the_joined_account_is_refused(): void
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(['status' => CampaignStatus::Active, 'requires_approval' => false]);

        $clipper = $this->clipper();
        $this->participations->join($campaign, $clipper, $this->account($clipper, Platform::TikTok));

        $this->expectException(ClipSubmissionRefused::class);
        $this->expectExceptionMessage('ne correspond pas');

        $this->submissions->submit($campaign, $clipper, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    }

    #[Test]
    public function the_same_post_cannot_be_submitted_twice_even_by_another_clipper(): void
    {
        $campaign = $this->campaign();
        $url = 'https://www.tiktok.com/@lina.clips/video/7123456789012345678';

        $first = $this->clipper();
        $this->participations->join($campaign, $first, $this->account($first));
        $this->submissions->submit($campaign, $first, $url);

        $second = $this->clipper();
        $this->participations->join($campaign, $second, $this->account($second));

        $this->expectException(ClipSubmissionRefused::class);
        $this->expectExceptionMessage('déjà été soumise');

        $this->submissions->submit($campaign, $second, $url);
    }

    #[Test]
    public function a_closed_campaign_refuses_submissions(): void
    {
        $campaign = $this->campaign();
        $clipper = $this->clipper();
        $this->participations->join($campaign, $clipper, $this->account($clipper));

        $campaign->forceFill(['status' => CampaignStatus::Paused])->save();

        $this->expectException(ClipSubmissionRefused::class);
        $this->expectExceptionMessage("n'accepte plus");

        $this->submissions->submit($campaign->fresh(), $clipper, 'https://www.tiktok.com/@l/video/7123456789012345678');
    }

    #[Test]
    public function a_submitted_clip_is_never_paid_before_moderation(): void
    {
        // Le moteur de budget est seul juge : même crédité, un clip non validé
        // ne rapporte rien. Ce test verrouille la frontière entre les modules.
        $campaign = $this->campaign();
        $clipper = $this->clipper();
        $this->participations->join($campaign, $clipper, $this->account($clipper));

        $clip = $this->submissions->submit($campaign, $clipper, 'https://www.tiktok.com/@l/video/7123456789012345678');

        $result = app(CampaignBudgetService::class)->creditViews($clip, 50_000, "clip:{$clip->id}:snapshot:1");

        $this->assertSame(0, $result->creditedCents);
        $this->assertSame(0, Clip::find($clip->id)->earned_cents);
        $this->assertSame(0, $campaign->fresh()->spent_cents);
    }
}
