<?php

namespace Tests\Feature\Admin;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Resources\Clippers\ClipperResource;
use App\Filament\Resources\Clippers\Pages\ViewClipper;
use App\Filament\Resources\Clippers\RelationManagers\ClipsRelationManager;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'admin ouvre un clippeur et retrouve tout ce qu'il a soumis : les vues, les
 * gains, et de quoi chercher et filtrer.
 */
class ClipperClipHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $clipper;

    protected Campaign $sound;

    protected Campaign $snap;

    protected Clip $big;

    protected Clip $small;

    protected Clip $rejected;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]));

        $this->clipper = User::factory()->create(['role' => UserRole::Clipper, 'name' => 'Yassine Elk']);
        $this->sound = Campaign::factory()->withRate(Platform::TikTok)->create(['title' => 'Défi Clip Sonore']);
        $this->snap = Campaign::factory()->withRate(Platform::TikTok)->create(['title' => 'Story Snapchat']);

        $base = ['user_id' => $this->clipper->id, 'platform' => Platform::TikTok, 'status' => ClipStatus::Approved, 'earned_cents' => 0];

        $this->big = Clip::factory()->create(array_merge($base, ['campaign_id' => $this->sound->id, 'views_total' => 139_000, 'earned_cents' => 4_200, 'posted_at' => now()->subDays(3)]));
        $this->small = Clip::factory()->create(array_merge($base, ['campaign_id' => $this->snap->id, 'views_total' => 295, 'posted_at' => now()->subDays(40)]));
        $this->rejected = Clip::factory()->create(array_merge($base, ['campaign_id' => $this->snap->id, 'views_total' => 12, 'status' => ClipStatus::Rejected, 'posted_at' => now()->subDays(1)]));

        // Les clips d'un autre clippeur ne doivent jamais apparaître.
        Clip::factory()->create(['campaign_id' => $this->sound->id, 'views_total' => 777_777, 'posted_at' => now()]);
    }

    protected function table()
    {
        return Livewire::test(ClipsRelationManager::class, [
            'ownerRecord' => $this->clipper,
            'pageClass' => ViewClipper::class,
        ]);
    }

    #[Test]
    public function the_clipper_page_lists_every_clip_of_that_clipper_only(): void
    {
        $this->table()
            ->assertOk()
            ->assertCanSeeTableRecords([$this->big, $this->small, $this->rejected])
            ->assertCountTableRecords(3)
            ->assertSee('Défi Clip Sonore')
            ->assertSee('139 000')
            ->assertDontSee('777 777');
    }

    #[Test]
    public function the_history_is_the_first_tab_of_the_clipper_page(): void
    {
        $relations = ClipperResource::getRelations();

        $this->assertSame(ClipsRelationManager::class, $relations[0]);
    }

    #[Test]
    public function it_can_be_filtered_by_campaign(): void
    {
        $this->table()
            ->filterTable('campaign_id', [$this->sound->id])
            ->assertCanSeeTableRecords([$this->big])
            ->assertCanNotSeeTableRecords([$this->small, $this->rejected]);
    }

    #[Test]
    public function it_can_be_filtered_by_status(): void
    {
        $this->table()
            ->filterTable('status', [ClipStatus::Rejected->value])
            ->assertCanSeeTableRecords([$this->rejected])
            ->assertCanNotSeeTableRecords([$this->big, $this->small]);
    }

    #[Test]
    public function it_can_be_filtered_by_views(): void
    {
        $this->table()
            ->filterTable('views_range', ['min' => 1000, 'max' => null])
            ->assertCanSeeTableRecords([$this->big])
            ->assertCanNotSeeTableRecords([$this->small, $this->rejected]);

        $this->table()
            ->filterTable('views_range', ['min' => null, 'max' => 300])
            ->assertCanSeeTableRecords([$this->small, $this->rejected])
            ->assertCanNotSeeTableRecords([$this->big]);
    }

    #[Test]
    public function it_can_be_filtered_by_publication_period(): void
    {
        $this->table()
            ->filterTable('posted_period', ['from' => now()->subDays(10)->toDateString(), 'until' => null])
            ->assertCanSeeTableRecords([$this->big, $this->rejected])
            ->assertCanNotSeeTableRecords([$this->small]);
    }

    #[Test]
    public function it_can_keep_only_clips_that_earned_money(): void
    {
        $this->table()
            ->filterTable('earned')
            ->assertCanSeeTableRecords([$this->big])
            ->assertCanNotSeeTableRecords([$this->small, $this->rejected]);
    }

    #[Test]
    public function it_can_be_searched_and_sorted_by_views(): void
    {
        $this->table()
            ->searchTable('Snapchat')
            ->assertCanSeeTableRecords([$this->small, $this->rejected])
            ->assertCanNotSeeTableRecords([$this->big]);

        $this->table()
            ->sortTable('views_total', 'desc')
            ->assertCanSeeTableRecords([$this->big, $this->small, $this->rejected], inOrder: true);
    }
}
