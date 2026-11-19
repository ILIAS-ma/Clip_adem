<?php

namespace Tests\Feature\Referrals;

use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Pages\ReferralBonuses;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Referrals\ReferralBonusService;
use App\Services\Referrals\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Parrainage suspendu (REFERRALS_ENABLED=false) : il disparaît du site sans rien supprimer, et revient en le réactivant. */
class ReferralPauseTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'country' => 'FR',
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'paypal_email' => 'p@paypal.test', 'profile_completed_at' => now(),
        ]);
        SocialAccount::factory()->create(['user_id' => $user->id, 'platform' => Platform::TikTok]);

        return $user;
    }

    #[Test]
    public function the_pages_are_gone_while_paused_and_back_when_enabled(): void
    {
        $user = $this->clipper();

        config()->set('clipping.referrals.enabled', false);
        $r = $this->actingAs($user)->get(route('referrals.index'));
        $this->assertSame(404, $r->status(), 'index: '.$r->status().' '.$r->headers->get('Location'));
        $r = $this->actingAs($user)->get(route('referrals.leaderboard'));
        $this->assertSame(404, $r->status(), 'leaderboard: '.$r->status().' '.$r->headers->get('Location'));
        $r = $this->actingAs($user)->get(route('dashboard'));
        $this->assertSame(200, $r->status(), 'dashboard: '.$r->status().' '.$r->headers->get('Location'));

        config()->set('clipping.referrals.enabled', true);
        $this->actingAs($user)->get(route('referrals.index'))->assertSuccessful();
    }

    #[Test]
    public function a_sign_up_is_not_attached_to_a_referrer_while_paused(): void
    {
        $parrain = $this->clipper();
        $code = app(ReferralService::class)->codeFor($parrain);
        $filleul = $this->clipper();

        config()->set('clipping.referrals.enabled', false);
        app(ReferralService::class)->attachFromRequest($filleul, Request::create('/', 'GET', ['parrain' => $code]));
        $this->assertNull($filleul->fresh()->referred_by);

        config()->set('clipping.referrals.enabled', true);
        app(ReferralService::class)->attachFromRequest($filleul, Request::create('/', 'GET', ['parrain' => $code]));
        $this->assertSame($parrain->id, (int) $filleul->fresh()->referred_by);
    }

    #[Test]
    public function no_bonus_is_paid_and_the_admin_page_is_closed_while_paused(): void
    {
        config()->set('clipping.referrals.enabled', false);

        $this->assertSame(0, app(ReferralBonusService::class)->pay(now()));

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $this->assertFalse($this->actingAs($admin)->get(ReferralBonuses::getUrl())->isSuccessful());
    }
}
