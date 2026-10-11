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
}
