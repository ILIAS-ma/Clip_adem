<?php

namespace Tests\Feature\Clipper;

use App\Enums\UserRole;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La garde d'accès de FetchClip.
 *
 * FetchClip tourne comme service Node à côté du site, sur 127.0.0.1:3000, et
 * n'a aucune authentification : il a été écrit pour une seule machine, la
 * sienne. Rien dans son code ne sait qui regarde — ni ses 26 routes d'API, ni
 * ses fichiers téléchargés.
 *
 * Nginx lui en pose une devant : avant chaque requête, il interroge cette
 * route (`auth_request`) et ne laisse passer que sur un 2xx. Elle est donc le
 * seul rempart entre l'outil et le premier passant, et c'est ce qui justifie
 * des tests sur une route de trois lignes.
 */
class StudioGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ces tests portent sur l'outil ouvert ; la pause a les siens plus bas.
        config()->set('clipping.studio.enabled', true);
    }

    #[Test]
    public function a_paused_studio_refuses_everyone_and_explains_why(): void
    {
        config()->set('clipping.studio.enabled', false);
        $clipper = $this->clipper();

        $this->actingAs($clipper)->get(route('studio.auth'))->assertUnauthorized();

        // La redirection de Nginx mène à l'explication, pas en boucle à la connexion.
        $this->actingAs($clipper)->get(route('studio.login'))->assertRedirect(route('studio.paused'));
        $this->actingAs($clipper)->get(route('studio.paused'))
            ->assertSuccessful()
            ->assertSee('Echo Studio est en pause');

        // Et le site ne propose plus d'y aller.
        $this->actingAs($clipper)->get(route('dashboard'))
            ->assertSuccessful()
            ->assertDontSee('/fetchclip', escape: false);
    }

    protected function clipper(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya.clips',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ], $attributs));
    }

    #[Test]
    public function a_clipper_is_let_through(): void
    {
        $this->actingAs($this->clipper())
            ->get('/_studio-auth')
            ->assertNoContent();
    }

    #[Test]
    public function a_visitor_is_refused_with_a_status_not_a_redirection(): void
    {
        /*
         * 401 et jamais 302 : `auth_request` traite tout ce qui n'est pas 2xx
         * comme un refus, mais une redirection vers l'écran de connexion lui
         * arriverait comme une réponse inattendue — Nginx rendrait alors une
         * erreur 500 au visiteur, qui conclurait à une panne du site.
         *
         * C'est pour la même raison que la route vit hors des groupes
         * `auth` : le middleware redirigerait au lieu de refuser.
         */
        $this->get('/_studio-auth')->assertUnauthorized();
    }

    #[Test]
    public function a_creator_is_refused(): void
    {
        // L'outil sert à monter des clips : un créateur paie pour qu'on le
        // fasse, il n'a rien à y faire — et son compte ne doit pas ouvrir un
        // espace de fichiers partagé.
        $creator = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        Creator::factory()->create(['user_id' => $creator->getKey(), 'is_active' => true]);

        $this->actingAs($creator)->get('/_studio-auth')->assertUnauthorized();
    }

    #[Test]
    public function a_banned_clipper_is_refused(): void
    {
        /*
         * Le contrôle est répété ici plutôt que délégué au middleware
         * `not.banned` : cette route est hors des groupes, et un bannissement
         * qui laisserait l'outil ouvert permettrait de continuer à produire
         * des clips avec les moyens de la plateforme.
         */
        $this->actingAs($this->clipper(['is_banned' => true]))
            ->get('/_studio-auth')
            ->assertUnauthorized();
    }

    #[Test]
    public function the_gate_tells_the_tool_who_is_working(): void
    {
        // L'identite decide de l'espace de travail : elle doit venir d'ici,
        // jamais du navigateur.
        $user = $this->clipper();

        $this->actingAs($user)
            ->get('/_studio-auth')
            ->assertNoContent()
            ->assertHeader('X-Clipper-Id', 'c'.$user->getKey());
    }

    #[Test]
    public function the_ping_keeps_a_clipper_session_alive(): void
    {
        /*
         * Sa reponse arrive au navigateur avec un cookie neuf : c'est ce qui
         * empeche la deconnexion en plein montage, la sous-requete de Nginx
         * ne renouvelant jamais rien.
         */
        $this->actingAs($this->clipper())
            ->get('/_studio-ping')
            ->assertNoContent();
    }

    #[Test]
    public function the_ping_does_not_keep_a_guest_alive(): void
    {
        // Sans session, le ping ne doit rien prolonger : le navigateur suit
        // la redirection, et l'outil sait alors afficher « session expirée ».
        // N'importe quelle redirection suffit : c'est `redirected` que le
        // script regarde, pas sa destination.
        $this->get('/_studio-ping')->assertRedirect();
    }

    #[Test]
    public function reconnecting_leads_back_to_the_tool(): void
    {
        // Sans cela, on revenait sur le tableau de bord en ayant perdu de vue
        // ce qu'on faisait.
        $this->get('/connexion-studio')->assertRedirect(route('login'));

        $this->assertSame(url('/fetchclip'), session('url.intended'));
    }
}
