<?php

namespace Tests\Feature\Clipper;

use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Livewire\CampaignCatalogue;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogueCategoriesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_catalogue_shows_the_categories_and_filters_on_one(): void
    {
        $youtube = Category::create(['name' => 'Youtube']);
        $streamer = Category::create(['name' => 'Streamer']);
        Campaign::factory()->create(['title' => 'Clip du kebab', 'status' => CampaignStatus::Active, 'category_id' => $youtube->id]);
        Campaign::factory()->create(['title' => 'Clip du live', 'status' => CampaignStatus::Active, 'category_id' => $streamer->id]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Clipper]));

        Livewire::test(CampaignCatalogue::class)
            ->assertSee('Youtube')
            ->assertSee('Streamer')
            ->assertSee('Clip du kebab')
            ->assertSee('Clip du live')
            ->set('category', $streamer->slug)
            ->assertSee('Clip du live')
            ->assertDontSee('Clip du kebab');
    }
}
