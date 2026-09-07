<?php

namespace Tests\Feature\Social;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\ClipSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cadence de relevé.
 *
 * Les vues d'un clip se font massivement dans les deux premiers jours : c'est
 * là que le relevé doit être serré, et c'est là que le clippeur regarde son
 * solde. Passé cette fenêtre, la courbe s'aplatit et interroger souvent ne
 * rend que le même nombre en brûlant du quota.
 */
class SyncCadenceTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 1_000_000,
            ]);
    }

    protected function clip(int $ageHours, int $syncedMinutesAgo): Clip
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);
        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        return Clip::factory()->create([
            'campaign_id' => $this->campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'posted_at' => now()->subHours($ageHours),
            'last_synced_at' => now()->subMinutes($syncedMinutesAgo),
        ]);
    }

    protected function isDue(Clip $clip): bool
    {
        $service = app(ClipSyncService::class);
        $method = new \ReflectionMethod($service, 'isDue');

        return $method->invoke($service, $clip, now());
    }

    #[Test]
    public function a_clip_from_this_morning_is_read_every_half_hour(): void
    {
        $this->assertFalse($this->isDue($this->clip(ageHours: 6, syncedMinutesAgo: 20)));
        $this->assertTrue($this->isDue($this->clip(ageHours: 6, syncedMinutesAgo: 40)));
    }

    #[Test]
    public function a_clip_from_last_week_keeps_the_slower_pace(): void
    {
        // Sa courbe est plate : le relever toutes les demi-heures rendrait le
        // même nombre en consommant le quota des clips récents.
        $this->assertFalse($this->isDue($this->clip(ageHours: 96, syncedMinutesAgo: 60)));
        $this->assertTrue($this->isDue($this->clip(ageHours: 96, syncedMinutesAgo: 200)));
    }

    #[Test]
    public function a_month_old_clip_is_read_once_a_day_at_most(): void
    {
        $this->assertFalse($this->isDue($this->clip(ageHours: 24 * 20, syncedMinutesAgo: 60 * 12)));
        $this->assertTrue($this->isDue($this->clip(ageHours: 24 * 20, syncedMinutesAgo: 60 * 25)));
    }

    #[Test]
    public function the_hot_pace_never_applies_to_a_clip_that_earns_nothing(): void
    {
        // Budget épuisé : les vues restent comptées, simplement moins souvent.
        // Sans ce garde-fou, une campagne à sec brûlerait le quota des autres.
        $this->campaign->forceFill(['spent_cents' => 1_000_000])->save();

        $this->assertFalse($this->isDue($this->clip(ageHours: 6, syncedMinutesAgo: 60)));
    }

    #[Test]
    public function the_most_recent_clips_are_served_first(): void
    {
        /*
         * Si le quota s'épuise en cours de passage, ce sont les clips dont les
         * vues bougent — et rapportent — qui doivent avoir été servis. Sans cet
         * ordre, un mois d'archives figées pourrait consommer le quota avant
         * que la publication d'hier soit relevée une seule fois.
         */
        $vieux = $this->clip(ageHours: 24 * 20, syncedMinutesAgo: 60 * 30);
        $recent = $this->clip(ageHours: 2, syncedMinutesAgo: 60);
        $moyen = $this->clip(ageHours: 96, syncedMinutesAgo: 60 * 5);

        $ordre = app(ClipSyncService::class)->dueClips(Platform::TikTok)->pluck('id')->all();

        $this->assertSame([$recent->id, $moyen->id, $vieux->id], $ordre);
    }

    #[Test]
    public function a_clip_never_read_is_always_due(): void
    {
        $clip = $this->clip(ageHours: 500, syncedMinutesAgo: 0);
        $clip->forceFill(['last_synced_at' => null])->save();

        $this->assertTrue($this->isDue($clip->fresh()));
    }
}
