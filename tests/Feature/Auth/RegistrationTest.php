<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_the_captcha_is_enforced_once_it_is_configured(): void
    {
        // La suite tourne captcha éteint — un client de test ne résout pas un
        // défi Cloudflare. Ce test rallume le dispositif pour vérifier qu'il
        // mord réellement : sans lui, une clé mal déployée passerait inaperçue.
        config(['services.turnstile.site_key' => '1x00000000000000000000AA']);

        $this->post('/register', [
            'first_name' => 'Robot',
            'last_name' => 'Anonyme',
            'email' => 'robot@example.com',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
        ])->assertSessionHasErrors('cf-turnstile-response');

        $this->assertGuest();
    }

    public function test_registration_stays_possible_when_no_captcha_key_is_deployed(): void
    {
        // Le contrôleur et la règle doivent dégrader de la même façon : exiger
        // un jeton que le widget ne peut pas produire bloquerait tout le monde
        // au lieu de laisser passer les robots.
        config(['services.turnstile.site_key' => null]);

        $this->post('/register', [
            'first_name' => 'Sans',
            'last_name' => 'Captcha',
            'email' => 'sans-captcha@example.com',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
        ]);

        $this->assertAuthenticated();
    }

    public function test_the_honeypot_swallows_a_bot_without_telling_it_why(): void
    {
        // Répondre « erreur de validation » apprendrait au robot quel champ
        // éviter. On répond comme si de rien n'était, et aucun compte n'est créé.
        $this->post('/register', [
            'first_name' => 'Robot',
            'last_name' => 'Anonyme',
            'email' => 'piege@example.com',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'website' => 'https://spam.example',
        ])->assertRedirect(route('register'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'piege@example.com']);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        // Sans rôle transmis, l'inscription retombe sur le profil le moins
        // privilégié et atterrit dans l'espace clippeur.
        $response->assertRedirect('/dashboard');
    }
}
