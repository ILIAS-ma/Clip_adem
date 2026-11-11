<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** On ne s'inscrit plus créateur : les comptes créateur sont ouverts par l'équipe. */
class NoCreatorSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function inscription(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Maya', 'last_name' => 'Bernard', 'email' => 'maya@clip.test',
            'password' => 'un-mot-de-passe-solide', 'password_confirmation' => 'un-mot-de-passe-solide',
            'phone' => '', 'phone_country' => 'FR', 'role' => 'clipper',
        ], $overrides);
    }

    #[Test]
    public function no_page_offers_the_creator_choice_any_more(): void
    {
        $this->get(route('register'))->assertSuccessful()->assertDontSee('Je suis créateur')->assertDontSee('Créateur');
        $this->get(route('login'))->assertSuccessful()->assertDontSee('Je suis créateur')->assertSee('Je suis clippeur');
        $this->get('/')->assertSuccessful()->assertDontSee('Je suis créateur');
    }

    #[Test]
    public function the_old_creator_link_opens_the_ordinary_clipper_form(): void
    {
        $this->get(route('register', ['profil' => 'creator']))
            ->assertSuccessful()
            ->assertDontSee('Créateur')
            ->assertSee('name="role" value="clipper"', false);
    }

    #[Test]
    public function registering_always_makes_a_clipper(): void
    {
        $this->post(route('register'), $this->inscription())->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Clipper, User::where('email', 'maya@clip.test')->sole()->role);
    }

    #[Test]
    public function a_forged_creator_request_is_refused(): void
    {
        $this->post(route('register'), $this->inscription(['role' => 'creator']))->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'maya@clip.test']);
    }
}
