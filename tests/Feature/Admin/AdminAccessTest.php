<?php

namespace Tests\Feature\Admin;

use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Campaign;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function panel()
    {
        return Filament::getPanel('admin');
    }

    protected function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $attributes));
    }

    #[Test]
    public function staff_can_reach_the_admin_panel(): void
    {
        $this->assertTrue($this->user(UserRole::SuperAdmin)->canAccessPanel($this->panel()));
        $this->assertTrue($this->user(UserRole::Moderator)->canAccessPanel($this->panel()));
    }

    #[Test]
    public function clippers_and_banned_accounts_cannot(): void
    {
        $this->assertFalse($this->user(UserRole::Clipper)->canAccessPanel($this->panel()));
        $this->assertFalse(
            $this->user(UserRole::SuperAdmin, ['is_banned' => true])->canAccessPanel($this->panel()),
        );
    }

    #[Test]
    public function the_admin_panel_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    #[Test]
    public function an_admin_without_two_factor_is_pushed_to_the_setup_screen(): void
    {
        // La 2FA est obligatoire : un compte admin compromis donne accès aux
        // paiements. Tant qu'elle n'est pas configurée, le panel est inutilisable.
        $admin = $this->user(UserRole::SuperAdmin);

        $this->assertNull($admin->getAppAuthenticationSecret());

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'multi-factor',
            $response->headers->get('Location'),
            'L\'admin aurait dû être redirigé vers la configuration de la 2FA.',
        );
    }

    #[Test]
    public function only_a_super_admin_may_delete_an_artist(): void
    {
        $artist = Artist::factory()->create();

        $this->assertTrue($this->user(UserRole::SuperAdmin)->can('delete', $artist));
        $this->assertFalse($this->user(UserRole::Moderator)->can('delete', $artist));
        $this->assertTrue($this->user(UserRole::Moderator)->can('update', $artist));
    }

    #[Test]
    public function a_campaign_that_already_spent_budget_cannot_be_deleted(): void
    {
        $superAdmin = $this->user(UserRole::SuperAdmin);

        $untouched = Campaign::factory()->create([
            'status' => CampaignStatus::Draft,
            'spent_cents' => 0,
        ]);
        $spent = Campaign::factory()->create([
            'status' => CampaignStatus::Paused,
            'spent_cents' => 4_200,
        ]);

        $this->assertTrue($superAdmin->can('delete', $untouched));
        $this->assertFalse($superAdmin->can('delete', $spent));
    }
}
