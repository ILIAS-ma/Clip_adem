<?php

namespace Tests\Feature\Referrals;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Pages\ReferralBonuses;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Referrals\ReferralBonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** La prime fixe : 7 € par filleul qui poste 12 clips validés dans le mois, versée une fois, sur le solde du parrain. */
class ReferralBonusTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('clipping.posting_window.enforced_from', now()->addYear()->toDateTimeString());
        $this->campaign = Campaign::factory()->create();
    }

    protected function clipper(?User $referrer = null, string $pseudo = 'x'): User
    {
        $user = User::factory()->create(['role' => UserRole::Clipper, 'pseudo' => $pseudo.fake()->unique()->numerify('###'), 'referred_by' => $referrer?->id, 'referred_at' => $referrer ? now() : null]);
        SocialAccount::factory()->create(['user_id' => $user->id, 'platform' => Platform::TikTok]);

        return $user;
    }

    protected function clips(User $user, int $count, ClipStatus $status = ClipStatus::Approved, $postedAt = null): void
    {
        for ($i = 0; $i < $count; $i++) {
            Clip::factory()->create([
                'campaign_id' => $this->campaign->id, 'user_id' => $user->id, 'platform' => Platform::TikTok,
                'social_account_id' => SocialAccount::where('user_id', $user->id)->value('id'),
                'status' => $status, 'views_total' => 100, 'earned_cents' => 0,
                'posted_at' => $postedAt ?? now()->subHour(),
                'url' => 'https://www.tiktok.com/@x/video/'.fake()->unique()->numerify('##########'),
            ]);
        }
    }

    #[Test]
    public function only_the_referrals_with_enough_validated_clips_in_the_month_earn_the_bonus(): void
    {
        $parrain = $this->clipper(null, 'parrain');
        $actif = $this->clipper($parrain, 'actif');
        $presque = $this->clipper($parrain, 'presque');
        $refus = $this->clipper($parrain, 'refus');
        $ancien = $this->clipper($parrain, 'ancien');
        $sansParrain = $this->clipper(null, 'seul');

        $this->clips($actif, 12);
        $this->clips($presque, 11);
        $this->clips($refus, 12, ClipStatus::Invalidated);
        $this->clips($ancien, 12, ClipStatus::Approved, now()->subMonths(2));
        $this->clips($sansParrain, 20);

        $service = app(ReferralBonusService::class);
        $rows = $service->qualifying(now());

        $this->assertSame([$actif->id], $rows->pluck('referred_id')->all());
        $this->assertSame($parrain->id, $rows->first()->referrer_id);
    }

    #[Test]
    public function the_bonus_lands_on_the_referrer_balance_once_per_referral(): void
    {
        $parrain = $this->clipper(null, 'parrain');
        $actif = $this->clipper($parrain, 'actif');
        $this->clips($actif, 12);

        $service = app(ReferralBonusService::class);

        $this->assertSame(1, $service->pay(now()));
        $this->assertSame(700, $parrain->fresh()->referralEarnedCents());
        $this->assertSame(700, $parrain->fresh()->availableBalanceCents());

        // Rejouer le versement, ou le refaire le mois suivant, ne paie pas deux fois le même filleul.
        $this->assertSame(0, $service->pay(now()));
        $this->clips($actif, 12, ClipStatus::Approved, now()->addMonth());
        $this->assertSame(0, $service->pay(now()->addMonth()));
        $this->assertSame(700, $parrain->fresh()->referralEarnedCents());
    }

    #[Test]
    public function a_banned_referrer_is_not_paid(): void
    {
        $parrain = $this->clipper(null, 'parrain');
        $parrain->forceFill(['is_banned' => true])->save();
        $actif = $this->clipper($parrain, 'actif');
        $this->clips($actif, 12);

        $this->assertCount(0, app(ReferralBonusService::class)->qualifying(now()));
    }

    #[Test]
    public function the_super_admin_pays_from_the_page_and_a_clipper_cannot_open_it(): void
    {
        $parrain = $this->clipper(null, 'parrain');
        $actif = $this->clipper($parrain, 'actif');
        $this->clips($actif, 12);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);

        Livewire::actingAs($admin)->test(ReferralBonuses::class)
            ->assertSee('À verser : 1 prime')
            ->call('pay')
            ->assertSee('Déjà versées ce mois-ci : 1');

        $this->assertSame(700, $parrain->fresh()->referralEarnedCents());

        $this->actingAs($parrain)->get(ReferralBonuses::getUrl())->assertForbidden();
    }

    #[Test]
    public function the_referral_page_announces_the_bonus_when_there_is_no_commission(): void
    {
        config()->set('clipping.referrals.rate_bp', 0);
        $user = $this->clipper(null, 'parrain');
        $user->forceFill([
            'email_verified_at' => now(), 'country' => 'FR', 'paypal_email' => 'p@paypal.test', 'profile_completed_at' => now(),
        ])->save();

        $this->actingAs($user)->get(route('referrals.index'))
            ->assertSuccessful()
            ->assertSee('7,00 €')
            ->assertSee('12 clips');
    }
}
