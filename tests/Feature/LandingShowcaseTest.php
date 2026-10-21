<?php

namespace Tests\Feature;

use App\Enums\ClipStatus;
use App\Enums\UserRole;
use App\Models\Clip;
use App\Models\User;
use App\Services\Reporting\LandingShowcase;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LandingShowcaseTest extends TestCase
{
    use RefreshDatabase;

    protected function clip(array $attributes = []): Clip
    {
        return Clip::factory()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Clipper])->getKey(),
            'status' => ClipStatus::Approved,
            'views_total' => 120_000,
            ...$attributes,
        ]);
    }

    #[Test]
    public function an_empty_site_shows_neither_counters_nor_wall(): void
    {
        $this->get('/')
            ->assertSuccessful()
            ->assertDontSee('id="en-direct"', escape: false)
            ->assertDontSee('id="clips"', escape: false);
    }

    #[Test]
    public function the_landing_page_shows_no_counters_even_with_real_activity(): void
    {
        $this->clip(['url' => 'https://www.tiktok.com/@a/video/5', 'views_total' => 2_100_000, 'featured_at' => now()]);

        $this->get('/')
            ->assertSuccessful()
            ->assertDontSee('clippeurs inscrits')
            ->assertDontSee('vues validées')
            ->assertDontSee('gagnés par les clippeurs');
    }

    #[Test]
    public function only_featured_approved_clips_reach_the_wall(): void
    {
        $featured = $this->clip(['url' => 'https://www.tiktok.com/@a/video/1', 'views_total' => 878_000, 'featured_at' => now()]);
        $this->clip(['url' => 'https://www.tiktok.com/@b/video/2']);
        $this->clip(['url' => 'https://www.tiktok.com/@c/video/3', 'status' => ClipStatus::Invalidated, 'featured_at' => now()]);
        $this->clip([
            'url' => 'https://www.tiktok.com/@d/video/4',
            'featured_at' => now(),
            'user_id' => User::factory()->create(['role' => UserRole::Clipper, 'is_banned' => true])->getKey(),
        ]);

        $this->assertSame([$featured->id], app(LandingShowcase::class)->wall()->pluck('id')->all());

        $this->get('/')
            ->assertSuccessful()
            ->assertSee('id="clips"', escape: false)
            ->assertSee('878k')
            ->assertSee('https://www.tiktok.com/@a/video/1', escape: false)
            ->assertDontSee('https://www.tiktok.com/@b/video/2', escape: false);
    }

    #[Test]
    public function the_wall_survives_a_cache_that_refuses_objects(): void
    {
        // La configuration de production : cache en base, aucune classe
        // désérialisable. Le deuxième affichage lit le cache.
        config()->set('cache.default', 'database');
        config()->set('cache.serializable_classes', false);

        $this->clip(['url' => 'https://www.tiktok.com/@a/video/9', 'views_total' => 500_000, 'featured_at' => now()]);

        $this->get('/')->assertSuccessful()->assertSee('500k');
        $this->get('/')->assertSuccessful()->assertSee('500k')->assertSee('TikTok');
    }

    #[Test]
    public function counters_only_count_validated_views(): void
    {
        $this->clip(['views_total' => 10_000]);
        $this->clip(['views_total' => 90_000, 'status' => ClipStatus::PendingReview]);

        $stats = app(LandingShowcase::class)->stats();

        $this->assertSame(10_000, $stats['views']);
        $this->assertSame(2, $stats['clippers']);
    }

    #[Test]
    public function featuring_copies_the_thumbnail_locally(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-jpeg', 200, ['Content-Type' => 'image/jpeg'])]);

        $clip = $this->clip(['thumbnail_url' => 'https://p16.tiktokcdn.com/thumb.jpeg?x-expires=1']);

        app(LandingShowcase::class)->feature($clip);

        $clip->refresh();
        $this->assertNotNull($clip->featured_at);
        $this->assertSame("featured/clip-{$clip->id}.jpg", $clip->featured_thumbnail_path);
        Storage::disk('public')->assertExists($clip->featured_thumbnail_path);

        app(LandingShowcase::class)->unfeature($clip);
        Storage::disk('public')->assertMissing("featured/clip-{$clip->id}.jpg");
        $this->assertNull($clip->fresh()->featured_at);
    }

    #[Test]
    public function a_failed_thumbnail_copy_still_features_the_clip(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('<html>', 200, ['Content-Type' => 'text/html'])]);

        $clip = $this->clip(['thumbnail_url' => 'https://p16.tiktokcdn.com/thumb.jpeg']);

        app(LandingShowcase::class)->feature($clip);

        $this->assertNotNull($clip->fresh()->featured_at);
        $this->assertNull($clip->fresh()->featured_thumbnail_path);
    }

    #[Test]
    public function views_are_shortened_like_on_the_platforms(): void
    {
        $this->assertSame('878k', Money::compactViews(878_400));
        $this->assertSame('1,2M', Money::compactViews(1_240_000));
        $this->assertSame('2M', Money::compactViews(2_000_000));
        $this->assertSame('950', Money::compactViews(950));
    }
}
