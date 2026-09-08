<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        // Soft delete et non suppression dure : les clips, les versements et le
        // grand livre référencent ce compte. Le compte disparaît des requêtes
        // ordinaires, mais la comptabilité reste justifiable.
        $this->assertSoftDeleted($user);
        $this->assertNull(User::find($user->id));
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_a_user_can_upload_an_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('moi.jpg'),
            ]);

        $response->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNotNull($user->avatar_url);
        Storage::disk('public')->assertExists('avatars/'.basename($user->avatar_url));
    }

    /**
     * Le SVG est le seul format volontairement exclu : servi depuis notre
     * domaine, il peut embarquer du script et s'exécuter dans le contexte
     * du site — même raison que pour les pièces jointes de campagne.
     */
    public function test_an_svg_avatar_is_refused(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
            ]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_uploading_a_new_avatar_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('un.jpg'),
        ]);
        $firstPath = 'avatars/'.basename($user->fresh()->avatar_url);

        $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('deux.jpg'),
        ]);

        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_a_user_can_remove_their_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('moi.jpg'),
        ]);
        $path = 'avatars/'.basename($user->fresh()->avatar_url);

        $this->actingAs($user)->delete('/profile/avatar');

        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_removing_a_google_avatar_does_not_touch_local_storage(): void
    {
        // L'URL Google n'est pas sur notre disque : il ne faut surtout pas
        // tenter d'y appliquer un chemin local et supprimer autre chose.
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_url' => 'https://lh3.googleusercontent.com/a/photo.jpg',
        ]);

        $this->actingAs($user)->delete('/profile/avatar');

        $this->assertNull($user->fresh()->avatar_url);
    }
}
