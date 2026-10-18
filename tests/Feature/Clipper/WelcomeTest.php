<?php

namespace Tests\Feature\Clipper;

use App\Enums\UserRole;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
            ...$attributes,
        ]);
    }

    #[Test]
    public function a_new_clipper_is_welcomed_once(): void
    {
        $clipper = $this->clipper();

        $this->actingAs($clipper)->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('welcome-title', escape: false)
            ->assertSee('Un souci, une question ?');

        $this->actingAs($clipper)->postJson(route('welcome.seen'))->assertNoContent();
        $this->assertNotNull($clipper->fresh()->welcome_seen_at);

        $this->actingAs($clipper->fresh())->get(route('dashboard'))
            ->assertSuccessful()
            ->assertDontSee('welcome-title', escape: false);
    }

    #[Test]
    public function the_help_button_follows_the_clipper_everywhere_but_the_messaging(): void
    {
        $clipper = $this->clipper(['welcome_seen_at' => now()]);

        foreach (['dashboard', 'campaigns.index', 'earnings.index'] as $route) {
            $this->actingAs($clipper)->get(route($route))
                ->assertSuccessful()
                ->assertSee("Besoin d'aide ?", escape: false);
        }

        $this->actingAs($clipper)->get(route('messages.index'))
            ->assertSuccessful()
            ->assertDontSee("Besoin d'aide ?", escape: false);
    }

    #[Test]
    public function creators_see_neither(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Creator, 'email_verified_at' => now()]);
        Creator::factory()->create(['user_id' => $creator->id, 'is_active' => true]);

        $this->actingAs($creator)->get(route('creator.dashboard'))
            ->assertSuccessful()
            ->assertDontSee('welcome-title', escape: false)
            ->assertDontSee("Besoin d'aide ?", escape: false);
    }
}
