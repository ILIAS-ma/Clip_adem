<?php

namespace Tests\Feature\Clipper;

use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Délier et relier un compte réseau.
 *
 * L'action existait, mais sous la forme d'un petit texte souligné en gris, et
 * derrière un `confirm()` du navigateur. Un clippeur dont le compte TikTok pose
 * problème doit pouvoir le refaire lui-même, sans écrire à quelqu'un : c'est
 * une action de premier plan, pas une note de bas de page.
 */
class UnlinkAccountTest extends TestCase
{
    use RefreshDatabase;

    protected User $clipper;

    protected SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya.clips',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ]);

        $this->account = SocialAccount::factory()->create([
            'user_id' => $this->clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);
    }

    #[Test]
    public function the_page_offers_both_unlinking_and_linking(): void
    {
        $this->actingAs($this->clipper)
            ->get(route('accounts.index'))
            ->assertSuccessful()
            ->assertSee('Délier')
            ->assertSee('Lier un autre compte');
    }

    #[Test]
    public function unlinking_works_without_javascript(): void
    {
        /*
         * La confirmation en deux temps est écrite en Alpine, donc en
         * JavaScript. Le formulaire, lui, doit rester un vrai formulaire : sans
         * cela, un navigateur sans JS afficherait un bouton inerte — et c'est
         * l'action qu'un clippeur en difficulté cherche en premier.
         */
        $this->actingAs($this->clipper)
            ->delete(route('accounts.destroy', $this->account))
            ->assertRedirect(route('accounts.index'));

        $this->assertFalse($this->account->fresh()->is_active);
    }

    #[Test]
    public function an_unlinked_account_can_be_linked_again(): void
    {
        $this->actingAs($this->clipper)->delete(route('accounts.destroy', $this->account));

        // Le compte délié reste listé, avec le chemin du retour : le faire
        // disparaître obligerait à repartir de « Liez votre premier compte »
        // alors que l'historique des clips, lui, pointe toujours dessus.
        $this->actingAs($this->clipper)
            ->get(route('accounts.index'))
            ->assertSuccessful()
            ->assertSee('Relier');
    }

    #[Test]
    public function nobody_unlinks_somebody_elses_account(): void
    {
        $intrus = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'intrus',
            'country' => 'FR',
            'paypal_email' => 'intrus@paypal.test',
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($intrus)
            ->delete(route('accounts.destroy', $this->account))
            ->assertForbidden();

        $this->assertTrue($this->account->fresh()->is_active);
    }

    #[Test]
    public function unlinking_keeps_the_clips_and_what_they_earned(): void
    {
        // Délier n'est pas effacer : c'est arrêter le relevé des vues. Reprendre
        // l'argent déjà gagné parce qu'on a délié un compte serait une sanction
        // que personne n'a décidée.
        $this->actingAs($this->clipper)->delete(route('accounts.destroy', $this->account));

        $this->actingAs($this->clipper)
            ->get(route('clips.index'))
            ->assertSuccessful();
    }
}
