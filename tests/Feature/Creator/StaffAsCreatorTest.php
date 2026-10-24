<?php

namespace Tests\Feature\Creator;

use App\Enums\UserRole;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un administrateur est aussi créateur : il a sa propre fiche et suit ses propres
 * campagnes, sans perdre le back-office ni son espace clippeur.
 */
class StaffAsCreatorTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create([
            'role' => $role,
            'email_verified_at' => now(),
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
    }

    #[Test]
    public function an_admin_without_a_profile_is_sent_to_create_one(): void
    {
        $admin = $this->staff();

        $this->actingAs($admin)->get('/createur')->assertRedirect(route('creator.profile.create'));
        $this->actingAs($admin)->get(route('creator.profile.create'))->assertOk();
    }

    #[Test]
    public function an_admins_creator_profile_is_active_at_once_without_validation(): void
    {
        // La validation des fiches est exigée : elle ne vaut pas pour qui les valide.
        config(['clipping.onboarding.require_creator_validation' => true]);
        $admin = $this->staff();

        $this->actingAs($admin)
            ->post(route('creator.profile.store'), ['name' => 'Le Boss', 'kind' => 'artist'])
            ->assertRedirect(route('creator.dashboard'));

        $creator = Creator::where('user_id', $admin->id)->firstOrFail();
        $this->assertTrue($creator->is_active);
        $this->assertSame('Le Boss', $creator->name);
    }

    #[Test]
    public function a_regular_creator_still_waits_for_validation(): void
    {
        config(['clipping.onboarding.require_creator_validation' => true]);
        $creator = User::factory()->create(['role' => UserRole::Creator, 'email_verified_at' => now()]);

        $this->actingAs($creator)->post(route('creator.profile.store'), ['name' => 'Nouvel Artiste', 'kind' => 'artist']);

        $this->assertFalse(Creator::where('user_id', $creator->id)->firstOrFail()->is_active);
    }

    #[Test]
    public function the_creator_space_offers_the_way_back_to_the_clipper_space_and_the_admin(): void
    {
        $admin = $this->staff();
        Creator::factory()->create(['user_id' => $admin->id, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('creator.dashboard'))
            ->assertOk()
            ->assertSee('Espace clippeur')
            ->assertSee('Administration')
            // Le solde et le moyen de paiement sont ceux du clippeur : pas ici.
            ->assertDontSee('Moyen de paiement');
    }

    #[Test]
    public function the_clipper_space_offers_the_creator_space_to_the_staff(): void
    {
        $admin = $this->staff();
        $admin->forceFill(['pseudo' => 'le.boss', 'country' => 'FR', 'paypal_email' => 'b@paypal.test'])->save();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Espace créateur')
            ->assertSee('Administration');
    }

    #[Test]
    public function the_admin_menu_links_to_the_creator_space(): void
    {
        config(['clipping.onboarding.require_admin_2fa' => false]);

        $this->actingAs($this->staff())->get('/admin')->assertSee('Espace créateur');
    }

    #[Test]
    public function a_moderator_is_also_a_creator(): void
    {
        $moderator = $this->staff(UserRole::Moderator);

        $this->actingAs($moderator)->get('/createur')->assertRedirect(route('creator.profile.create'));
    }

    #[Test]
    public function a_clipper_still_cannot_enter_the_creator_space(): void
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper, 'email_verified_at' => now()]);

        $this->actingAs($clipper)->get('/createur')->assertRedirect('/dashboard');
    }

    #[Test]
    public function a_creator_still_stays_out_of_the_clipper_space(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Creator, 'email_verified_at' => now()]);
        Creator::factory()->create(['user_id' => $creator->id, 'is_active' => true]);

        $this->actingAs($creator)->get('/dashboard')->assertRedirect('/createur');
    }
}
