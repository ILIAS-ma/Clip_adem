<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Creators\Pages\CreateCreator;
use App\Models\Category;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'identifiant d'un créateur n'apparaît dans aucune adresse : il n'est plus
 * demandé, il se fabrique depuis le nom.
 */
class CreatorSlugTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_admin_creates_a_creator_with_just_a_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        Creator::factory()->create(['name' => 'Saya', 'slug' => 'saya']);

        Livewire::actingAs($admin)
            ->test(CreateCreator::class)
            ->assertFormFieldDoesNotExist('slug')
            ->fillForm(['kind' => 'artist', 'name' => 'Saya'])
            ->call('create')
            ->assertHasNoFormErrors();

        // Homonyme : l'identifiant se décale tout seul au lieu de bloquer.
        $this->assertSame(['saya', 'saya-2'], Creator::orderBy('id')->pluck('slug')->all());
    }

    #[Test]
    public function any_creation_path_gets_an_identifier(): void
    {
        $this->assertSame('ete-2026', Creator::create(['name' => 'Été 2026 !'])->slug);
    }

    #[Test]
    public function the_admin_creates_a_category_with_just_a_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        Category::create(['name' => 'Gaming']);

        Livewire::actingAs($admin)
            ->test(CreateCategory::class)
            ->assertFormFieldDoesNotExist('slug')
            ->fillForm(['name' => 'Gaming'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['gaming', 'gaming-2'], Category::orderBy('id')->pluck('slug')->all());
    }
}
