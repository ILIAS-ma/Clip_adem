<?php

namespace Tests\Feature\Admin;

use App\Enums\ModerationAction;
use App\Enums\UserRole;
use App\Filament\Resources\Clippers\Pages\ListClippers;
use App\Filament\Resources\Clippers\Pages\ViewClipper;
use App\Models\ModerationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Un compte clippeur sans numéro de téléphone : réservé au super-admin, tracé, et sans toucher à la règle publique. */
class PhoneExemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('clipping.onboarding.require_phone_verification', true);
    }

    protected function admin(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create(['role' => $role, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
    }

    protected function data(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Second Compte',
            'pseudo' => 'second.compte',
            'email' => 'second@example.com',
            'country' => 'FR',
            'password' => 'un-mot-de-passe-solide',
        ], $overrides);
    }

    #[Test]
    public function the_super_admin_creates_a_clipper_without_any_phone_number(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(ListClippers::class)
            ->callAction('createWithoutPhone', $this->data())
            ->assertHasNoActionErrors();

        $user = User::where('email', 'second@example.com')->firstOrFail();

        $this->assertSame(UserRole::Clipper, $user->role);
        $this->assertNull($user->phone);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->isPhoneExempt());
        $this->assertTrue($user->hasVerifiedPhone());

        $log = ModerationLog::where('action', ModerationAction::PhoneExemptionGranted)->sole();
        $this->assertSame($user->id, $log->subject_id);
        $this->assertSame($admin->id, $log->user_id);
    }

    #[Test]
    public function that_account_gets_into_the_site_without_the_sms_code(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(ListClippers::class)->callAction('createWithoutPhone', $this->data());

        $this->flushSession();
        auth()->logout();

        $response = $this->actingAs(User::where('email', 'second@example.com')->firstOrFail())->get(route('dashboard'));

        // Il peut être renvoyé vers son profil à compléter, jamais vers la vérification du téléphone.
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString(route('phone.verify', absolute: false), $location);
    }

    #[Test]
    public function an_ordinary_clipper_without_a_verified_phone_is_still_sent_to_the_verification(): void
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper, 'email_verified_at' => now(), 'phone' => null, 'phone_verified_at' => null]);

        $this->actingAs($clipper)->get(route('dashboard'))->assertRedirect(route('phone.verify'));
    }

    #[Test]
    public function a_moderator_cannot_create_or_exempt_anybody(): void
    {
        $this->actingAs($this->admin(UserRole::Moderator));

        Livewire::test(ListClippers::class)->assertActionHidden('createWithoutPhone');

        $clipper = User::factory()->create(['role' => UserRole::Clipper]);

        Livewire::test(ViewClipper::class, ['record' => $clipper->getRouteKey()])->assertActionHidden('togglePhoneExemption');
    }

    #[Test]
    public function the_email_and_pseudo_must_be_free(): void
    {
        User::factory()->create(['email' => 'second@example.com', 'pseudo' => 'second.compte']);
        $this->actingAs($this->admin());

        Livewire::test(ListClippers::class)
            ->callAction('createWithoutPhone', $this->data())
            ->assertHasActionErrors(['email', 'pseudo']);

        $this->assertSame(0, ModerationLog::where('action', ModerationAction::PhoneExemptionGranted)->count());
    }

    #[Test]
    public function an_existing_account_can_be_exempted_and_the_number_demanded_again(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $clipper = User::factory()->create(['role' => UserRole::Clipper, 'phone' => null, 'phone_verified_at' => null]);

        $page = Livewire::test(ViewClipper::class, ['record' => $clipper->getRouteKey()]);

        $page->callAction('togglePhoneExemption');
        $this->assertTrue($clipper->fresh()->isPhoneExempt());

        $page->callAction('togglePhoneExemption');
        $this->assertFalse($clipper->fresh()->isPhoneExempt());
        $this->assertFalse($clipper->fresh()->hasVerifiedPhone());

        $this->assertSame(
            [ModerationAction::PhoneExemptionGranted, ModerationAction::PhoneExemptionRemoved],
            ModerationLog::orderBy('id')->pluck('action')->all(),
        );
    }

    #[Test]
    public function a_verified_phone_still_counts_as_verified(): void
    {
        $verified = User::factory()->create(['phone' => '+33611223344', 'phone_verified_at' => now()]);

        $this->assertTrue($verified->hasVerifiedPhone());
        $this->assertFalse($verified->isPhoneExempt());
    }
}
