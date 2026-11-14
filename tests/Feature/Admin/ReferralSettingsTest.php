<?php

namespace Tests\Feature\Admin;

use App\Enums\ModerationAction;
use App\Enums\UserRole;
use App\Filament\Pages\ReferralSettings;
use App\Models\ModerationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Referrals\ReferrerLeaderboard;
use App\Services\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Les conditions du parrainage, choisies par l'équipe depuis l'administration. */
class ReferralSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create(['role' => $role, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
    }

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => 'maya.clips',
            'country' => 'FR', 'paypal_email' => 'maya@paypal.test', 'profile_completed_at' => now(),
        ]);
    }

    protected function values(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'commission_percent' => 3.5,
            'active_min_clips' => 8,
            'active_min_views' => 2500,
            'active_min_days' => 3,
            'leaderboard_prize' => '20 € au meilleur parrain du mois',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Le service : la base recouvre la configuration
    // ------------------------------------------------------------------

    #[Test]
    public function saved_settings_cover_the_configuration_at_start_up(): void
    {
        $this->assertSame(500, (int) config('clipping.referrals.rate_bp'));

        Setting::create(['key' => 'referrals.rate_bp', 'value' => json_encode(250)]);
        Setting::create(['key' => 'referrals.active_min_clips', 'value' => json_encode(9)]);
        Cache::forget('site-settings');

        app(SiteSettings::class)->apply();

        $this->assertSame(250, config('clipping.referrals.rate_bp'));
        $this->assertSame(9, config('clipping.referrals.active_min_clips'));
        // Ce qui n'est pas enregistré garde la valeur d'origine.
        $this->assertSame(1000, (int) config('clipping.referrals.active_min_views'));
    }

    #[Test]
    public function an_unknown_setting_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SiteSettings::class)->save(['database.default' => 'autre'], $this->admin());
    }

    #[Test]
    public function resetting_goes_back_to_the_original_values(): void
    {
        $admin = $this->admin();
        $service = app(SiteSettings::class);

        $service->save(['referrals.rate_bp' => 1200], $admin);
        $this->assertSame(1200, config('clipping.referrals.rate_bp'));

        $service->reset(['referrals.rate_bp'], $admin);

        $this->assertSame(500, (int) config('clipping.referrals.rate_bp'));
        $this->assertDatabaseMissing('settings', ['key' => 'referrals.rate_bp']);
    }

    // ------------------------------------------------------------------
    // La page d'administration
    // ------------------------------------------------------------------

    #[Test]
    public function only_the_super_admin_can_open_the_page(): void
    {
        $this->actingAs($this->admin(UserRole::Moderator))->get('/admin/parrainage')->assertForbidden();
        $this->actingAs($this->clipper())->get('/admin/parrainage')->assertForbidden();

        $this->actingAs($this->admin())->get('/admin/parrainage')->assertSuccessful()->assertSee('Parrainage');
    }

    #[Test]
    public function saving_changes_the_rule_the_commission_and_the_prize_everywhere(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(ReferralSettings::class)
            ->fillForm($this->values())
            ->call('save')
            ->assertHasNoFormErrors();

        // La configuration vivante suit…
        $this->assertSame(350, config('clipping.referrals.rate_bp'));
        $this->assertSame(['clips' => 8, 'views' => 2500, 'days' => 3], ReferrerLeaderboard::rule());
        $this->assertSame('20 € au meilleur parrain du mois', config('clipping.referrals.leaderboard_prize'));

        // …et tout est gardé en base, daté, avec son auteur.
        // Cinq réglages enregistrés : « parrainage actif » n'a pas changé, il garde sa valeur d'origine.
        $this->assertSame(5, Setting::count());
        $this->assertSame($admin->id, Setting::where('key', 'referrals.rate_bp')->value('updated_by'));

        $log = ModerationLog::where('action', ModerationAction::SettingsChanged)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(350, $log->meta['changes']['referrals.rate_bp']['après']);
        $this->assertSame(500, $log->meta['changes']['referrals.rate_bp']['avant']);
    }

    #[Test]
    public function the_clipper_pages_show_the_new_conditions(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(ReferralSettings::class)->fillForm($this->values())->call('save');

        $clipper = $this->clipper();

        $this->actingAs($clipper)->get(route('referrals.index'))
            ->assertSuccessful()
            ->assertSee('3,5 %');

        $this->actingAs($clipper)->get(route('referrals.leaderboard'))
            ->assertSuccessful()
            ->assertSee('8 clips validés')
            ->assertSee('2 500 vues')
            ->assertSee('3 jours différents')
            ->assertSee('20 € au meilleur parrain du mois');
    }

    #[Test]
    public function an_out_of_range_commission_is_refused(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ReferralSettings::class)
            ->fillForm($this->values(['commission_percent' => 25]))
            ->call('save')
            ->assertHasFormErrors(['commission_percent']);

        $this->assertSame(0, Setting::count());
        $this->assertSame(500, (int) config('clipping.referrals.rate_bp'));
    }

    #[Test]
    public function the_reset_button_restores_everything(): void
    {
        $this->actingAs($this->admin());

        $page = Livewire::test(ReferralSettings::class)->fillForm($this->values())->call('save');
        $this->assertSame(350, config('clipping.referrals.rate_bp'));

        $page->call('resetToDefaults');

        $this->assertSame(0, Setting::count());
        $this->assertSame(500, (int) config('clipping.referrals.rate_bp'));
        $this->assertSame(['clips' => 5, 'views' => 1000, 'days' => 2], ReferrerLeaderboard::rule());
    }

    // ------------------------------------------------------------------
    // L'onglet du menu
    // ------------------------------------------------------------------

    #[Test]
    public function the_clipper_menu_has_a_referral_tab_until_the_team_switches_it_off(): void
    {
        $clipper = $this->clipper();

        $this->actingAs($clipper)->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Parrainage')
            ->assertSee(route('referrals.index'), false);

        $this->actingAs($this->admin());
        Livewire::test(ReferralSettings::class)->fillForm($this->values(['enabled' => false]))->call('save');

        $this->actingAs($clipper)->get(route('dashboard'))
            ->assertSuccessful()
            ->assertDontSee(route('referrals.index'), false);
    }
}
