<?php

namespace Tests\Feature\Moderation;

use App\Models\Clip;
use App\Models\ClipViewSnapshot;
use App\Models\SocialAccount;
use App\Services\Moderation\SuspiciousViewsDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SuspiciousViewsDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected SuspiciousViewsDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = app(SuspiciousViewsDetector::class);
    }

    protected function snapshots(Clip $clip, array $pairs): void
    {
        foreach ($pairs as [$views, $capturedAt]) {
            ClipViewSnapshot::factory()->create([
                'clip_id' => $clip->getKey(),
                'views' => $views,
                'captured_at' => $capturedAt,
            ]);
        }
    }

    #[Test]
    public function a_sudden_spike_is_flagged(): void
    {
        $clip = Clip::factory()->create(['posted_at' => now()->subDays(3)]);

        $this->snapshots($clip, [
            [4_000, now()->subDays(2)],
            [90_000, now()->subDays(2)->addHours(2)], // ×22 en 2 h
        ]);

        $flags = $this->detector->flags($clip);

        $this->assertNotEmpty($flags);
        $this->assertStringContainsString('86 000 vues', $flags[0]);
    }

    #[Test]
    public function steady_organic_growth_is_not_flagged(): void
    {
        $clip = Clip::factory()->create(['posted_at' => now()->subDays(5)]);

        $this->snapshots($clip, [
            [12_000, now()->subDays(4)],
            [26_000, now()->subDays(3)],
            [41_000, now()->subDays(2)],
            [58_000, now()->subDay()],
        ]);

        $this->assertFalse($this->detector->isSuspicious($clip));
    }

    #[Test]
    public function small_numbers_never_trigger_the_spike_rule(): void
    {
        // Passer de 10 à 900 vues, c'est ×90 — et parfaitement banal.
        $clip = Clip::factory()->create(['posted_at' => now()->subDays(2)]);

        $this->snapshots($clip, [
            [10, now()->subDay()],
            [900, now()->subDay()->addHour()],
        ]);

        $this->assertFalse($this->detector->isSuspicious($clip));
    }

    #[Test]
    public function an_impossible_cold_start_is_flagged(): void
    {
        $clip = Clip::factory()->create(['posted_at' => now()->subDays(2)]);

        $this->snapshots($clip, [
            [120_000, now()->subDays(2)->addMinutes(30)],
        ]);

        $flags = $this->detector->flags($clip);

        $this->assertNotEmpty($flags);
        $this->assertStringContainsString('premières minutes', implode(' ', $flags));
    }

    #[Test]
    public function an_absurd_views_to_followers_ratio_is_flagged(): void
    {
        $account = SocialAccount::factory()->create(['followers_count' => 300]);

        $clip = Clip::factory()->create([
            'social_account_id' => $account->getKey(),
            'user_id' => $account->user_id,
            'views_total' => 900_000, // 3 000 vues par abonné
            'posted_at' => now()->subDay(),
        ]);

        $flags = $this->detector->flags($clip);

        $this->assertStringContainsString('vues par abonné', implode(' ', $flags));
    }

    #[Test]
    public function the_sql_filter_returns_the_same_spiking_clips(): void
    {
        $spiking = Clip::factory()->create(['posted_at' => now()->subDays(3)]);
        $this->snapshots($spiking, [
            [3_000, now()->subDays(2)],
            [80_000, now()->subDays(2)->addHours(3)],
        ]);

        $healthy = Clip::factory()->create(['posted_at' => now()->subDays(3)]);
        $this->snapshots($healthy, [
            [20_000, now()->subDays(2)],
            [27_000, now()->subDays(2)->addHours(3)],
        ]);

        $ids = $this->detector->scopeSuspicious(Clip::query())->pluck('id');

        $this->assertTrue($ids->contains($spiking->getKey()));
        $this->assertFalse($ids->contains($healthy->getKey()));
    }
}
