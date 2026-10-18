<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\ParticipationStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Resources\Participations\Pages\ListParticipations;
use App\Filament\Resources\Participations\ParticipationResource;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\ParticipationReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParticipationReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
    }

    protected function pending(): CampaignParticipation
    {
        $clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => fake()->unique()->userName(),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ]);

        $campaign = Campaign::factory()->withRate(Platform::TikTok)->create([
            'status' => CampaignStatus::Active,
            'requires_approval' => true,
        ]);

        $account = SocialAccount::factory()->create(['user_id' => $clipper->id, 'platform' => Platform::TikTok]);

        return CampaignParticipation::create([
            'campaign_id' => $campaign->id,
            'user_id' => $clipper->id,
            'social_account_id' => $account->id,
            'status' => ParticipationStatus::Pending,
            'applied_at' => now(),
        ]);
    }

    #[Test]
    public function the_admin_sees_pending_requests_and_accepts_them(): void
    {
        $participation = $this->pending();
        $admin = $this->admin();

        $this->assertSame('1', ParticipationResource::getNavigationBadge());
        $this->actingAs($admin)->get(ParticipationResource::getUrl())->assertSuccessful();

        Livewire::actingAs($admin)
            ->test(ListParticipations::class)
            ->assertCanSeeTableRecords([$participation])
            ->callTableAction('approveParticipation', $participation);

        $this->assertSame(ParticipationStatus::Approved, $participation->fresh()->status);
        $this->assertNotNull($participation->fresh()->approved_at);
        $this->assertNull(ParticipationResource::getNavigationBadge());
        Notification::assertSentTo($participation->user, ParticipationReviewed::class, fn ($n) => $n->accepted);
    }

    #[Test]
    public function a_refusal_carries_its_reason_to_the_clipper(): void
    {
        $participation = $this->pending();

        Livewire::actingAs($this->admin())
            ->test(ListParticipations::class)
            ->callTableAction('rejectParticipation', $participation, ['reason' => 'Compte trop récent'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ParticipationStatus::Rejected, $participation->fresh()->status);
        Notification::assertSentTo($participation->user, ParticipationReviewed::class, fn ($n) => ! $n->accepted);

        $this->actingAs($participation->user)
            ->get(route('campaigns.show', $participation->campaign))
            ->assertSee('Refusée')
            ->assertSee('Compte trop récent');
    }

    #[Test]
    public function the_clipper_understands_what_pending_means(): void
    {
        $participation = $this->pending();

        $this->actingAs($participation->user)
            ->get(route('campaigns.show', $participation->campaign))
            ->assertSuccessful()
            ->assertSee('En attente')
            ->assertSee("L'équipe valide les participations", escape: false)
            ->assertSee('participent déjà à cette campagne')
            ->assertDontSee("n'est compatible", escape: false);
    }

    #[Test]
    public function the_campaign_page_lists_its_requests(): void
    {
        $participation = $this->pending();

        $this->actingAs($this->admin())
            ->get(route('filament.admin.resources.campaigns.edit', $participation->campaign))
            ->assertSuccessful()
            ->assertSee('Participations');
    }
}
