<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Livewire\SubmitClip;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clips\ClipSubmissionService;
use App\Services\Clips\ParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Une campagne peut limiter le nombre de vidéos envoyées d'un coup. */
class SubmissionBatchLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.tiktok.client_key', null);
    }

    protected function picker(?int $limit, ?int $perHour = null): array
    {
        $campaign = Campaign::factory()->contest([10_000])->create([
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subHours(150),
            'ends_at' => now()->addDays(5),
            'max_clips_per_submission' => $limit,
            'max_clips_per_hour' => $perHour,
        ]);
        $clipper = User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => 'lea'.fake()->unique()->numberBetween(1, 9999),
            'country' => 'FR', 'paypal_email' => fake()->unique()->safeEmail(),
        ]);
        $account = SocialAccount::factory()->create(['user_id' => $clipper->id, 'platform' => Platform::TikTok]);
        app(ParticipationService::class)->join($campaign, $clipper, $account);

        $page = Livewire::actingAs($clipper)->test(SubmitClip::class, ['campaign' => $campaign])->call('loadVideos');
        $keys = collect($page->get('groups')[0]['videos'])->where('eligible', true)->where('submitted', false)
            ->map(fn ($v) => $account->id.':'.$v['id'])->values();

        return [$campaign, $page, $keys];
    }

    #[Test]
    public function more_videos_than_the_limit_are_refused_as_a_whole_with_a_clear_message(): void
    {
        [, $page, $keys] = $this->picker(3);
        $this->assertGreaterThan(5, $keys->count());

        $page->call('save', $keys->take(4)->all(), [])->assertNoRedirect();

        $this->assertSame(0, Clip::count());
        $this->assertStringContainsString('limite chaque envoi à 3 vidéos', (string) $page->get('pickError'));
        $this->assertStringContainsString('tu en as choisi 4', (string) $page->get('pickError'));
    }

    #[Test]
    public function exactly_the_limit_goes_through_and_the_rest_can_follow_in_a_second_batch(): void
    {
        [$campaign, $page, $keys] = $this->picker(3);

        $page->call('save', $keys->take(3)->all(), [])->assertRedirect(route('campaigns.show', $campaign));
        $this->assertSame(3, Clip::count());

        $page->call('loadVideos')->call('save', $keys->slice(3, 3)->all(), [])->assertRedirect(route('campaigns.show', $campaign));
        $this->assertSame(6, Clip::count());
    }

    #[Test]
    public function a_campaign_without_limit_accepts_any_number(): void
    {
        [$campaign, $page, $keys] = $this->picker(null);

        $page->call('save', $keys->take(8)->all(), [])->assertRedirect(route('campaigns.show', $campaign));

        $this->assertSame(8, Clip::count());
        $this->assertNull($campaign->submissionBatchLimit());
    }

    #[Test]
    public function one_video_per_hour_lets_the_first_through_and_blocks_the_second_with_the_time_to_wait(): void
    {
        [$campaign, $page, $keys] = $this->picker(8, 1);

        $page->call('save', $keys->take(1)->all(), [])->assertRedirect(route('campaigns.show', $campaign));
        $this->assertSame(1, Clip::count());

        $second = $page->call('loadVideos')->call('save', $keys->slice(1, 1)->all(), [])->assertNoRedirect();

        $this->assertSame(1, Clip::count());
        $this->assertStringContainsString('1 vidéo par heure', (string) $second->get('pickError'));
        $this->assertStringContainsString('Tu pourras en envoyer une autre à', (string) $second->get('pickError'));
    }

    #[Test]
    public function the_hourly_limit_frees_up_once_the_last_submission_is_over_an_hour_old(): void
    {
        [$campaign, $page, $keys] = $this->picker(8, 1);

        $page->call('save', $keys->take(1)->all(), []);
        Clip::query()->update(['submitted_at' => now()->subMinutes(61)]);

        $page->call('loadVideos')->call('save', $keys->slice(1, 1)->all(), [])->assertRedirect(route('campaigns.show', $campaign));

        $this->assertSame(2, Clip::count());
    }

    #[Test]
    public function several_videos_per_hour_count_what_is_left(): void
    {
        [, $page, $keys] = $this->picker(null, 3);

        $page->call('save', $keys->take(2)->all(), []);
        $this->assertSame(2, Clip::count());

        $refused = $page->call('loadVideos')->call('save', $keys->slice(2, 2)->all(), []);

        $this->assertSame(2, Clip::count());
        $this->assertStringContainsString('tu peux en envoyer 1 maintenant', (string) $refused->get('pickError'));
    }

    #[Test]
    public function the_hourly_limit_is_per_clipper_not_shared(): void
    {
        [$campaign, , $keys] = $this->picker(null, 1);

        $other = User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => 'autre'.fake()->unique()->numberBetween(1, 9999),
            'country' => 'FR', 'paypal_email' => fake()->unique()->safeEmail(),
        ]);

        $this->assertNull(app(ClipSubmissionService::class)->nextSlotAt($campaign, $other));
        $this->assertSame(1, app(ClipSubmissionService::class)->allowance($campaign, $other));
        $this->assertNotEmpty($keys);
    }

    #[Test]
    public function the_picker_tells_the_browser_about_the_limit(): void
    {
        [, $page] = $this->picker(8);

        $page->assertSee('limit: 8', false);
    }
}
