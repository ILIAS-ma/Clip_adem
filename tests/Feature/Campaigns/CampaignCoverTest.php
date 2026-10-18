<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignCoverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_admin_uploads_a_cover_shown_to_clippers(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $campaign = Campaign::factory()->withRate()->create(['status' => CampaignStatus::Active]);

        Livewire::actingAs($admin)
            ->test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            // Une image carrée passe : le format paysage est conseillé, pas imposé.
            ->fillForm(['cover_path' => UploadedFile::fake()->image('couverture.jpg', 800, 800)])
            ->call('save')
            ->assertHasNoFormErrors();

        $campaign->refresh();
        $this->assertNotNull($campaign->cover_path);
        Storage::disk('public')->assertExists($campaign->cover_path);

        $clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($clipper)->get(route('campaigns.index'))
            ->assertSuccessful()
            ->assertSee($campaign->coverUrl(), escape: false);

        $this->actingAs($clipper)->get(route('campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee($campaign->coverUrl(), escape: false);
    }

    #[Test]
    public function a_campaign_without_cover_still_renders(): void
    {
        $campaign = Campaign::factory()->withRate()->create(['status' => CampaignStatus::Active]);

        $this->assertNull($campaign->coverUrl());
    }
}
