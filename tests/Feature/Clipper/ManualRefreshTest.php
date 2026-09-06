<?php

namespace Tests\Feature\Clipper;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\ClipSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le bouton « Actualiser les vues ».
 *
 * Aucune plateforme ne pousse son compteur de vues — il n'existe pas de
 * webhook chez TikTok. Entre deux passages automatiques, c'est le seul recours
 * d'un clippeur qui veut savoir où il en est. Le délai de garde est ce qui
 * l'empêche de devenir une attaque sur notre propre quota d'API.
 */
class ManualRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ]);
    }

    protected function clipFor(User $clipper, array $attributes = []): Clip
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);

        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        return Clip::factory()->create(array_merge([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => 0,
            'posted_at' => now()->subDays(3),
            'last_synced_at' => null,
        ], $attributes));
    }

    #[Test]
    public function a_clipper_can_pull_the_latest_views_of_their_own_clip(): void
    {
        $clipper = $this->clipper();
        $clip = $this->clipFor($clipper);

        $this->actingAs($clipper)
            ->post(route('clips.refresh', $clip))
            ->assertRedirect();

        $clip->refresh();

        $this->assertNotNull($clip->last_synced_at);
        // Le fournisseur simulé rend une courbe croissante : trois jours après
        // publication, le compteur n'est plus à zéro.
        $this->assertGreaterThan(0, $clip->views_total);
    }

    #[Test]
    public function the_refreshed_views_are_credited_straight_away(): void
    {
        // Tout l'intérêt du bouton : voir son solde bouger, pas seulement son
        // compteur de vues.
        $clipper = $this->clipper();
        $clip = $this->clipFor($clipper);

        $this->actingAs($clipper)->post(route('clips.refresh', $clip));

        $this->assertGreaterThan(0, $clip->fresh()->earned_cents);
    }

    #[Test]
    public function pressing_twice_in_a_row_changes_nothing(): void
    {
        $clipper = $this->clipper();
        $clip = $this->clipFor($clipper, ['last_synced_at' => now()->subMinute()]);

        $this->actingAs($clipper)
            ->post(route('clips.refresh', $clip))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Déjà relevé'));

        // Le délai de garde protège le quota : sans lui, un clic répété
        // brûlerait les appels dont les autres clippeurs ont besoin.
        $this->assertSame(0, $clip->fresh()->views_total);
    }

    #[Test]
    public function the_cooldown_lets_go_once_it_has_passed(): void
    {
        $clipper = $this->clipper();
        $cooldown = (int) config('clipping.sync.manual_cooldown_minutes');

        $clip = $this->clipFor($clipper, [
            'last_synced_at' => now()->subMinutes($cooldown + 1),
        ]);

        $this->actingAs($clipper)->post(route('clips.refresh', $clip));

        $this->assertGreaterThan(0, $clip->fresh()->views_total);
    }

    #[Test]
    public function nobody_refreshes_somebody_elses_clip(): void
    {
        // Sinon on pourrait épuiser le quota d'un compte lié qui n'est pas le
        // sien, et lire au passage l'activité d'un concurrent.
        $clip = $this->clipFor($this->clipper());

        $this->actingAs($this->clipper())
            ->post(route('clips.refresh', $clip))
            ->assertForbidden();
    }

    #[Test]
    public function a_clipper_can_analyze_their_own_clip(): void
    {
        $clipper = $this->clipper();
        $clip = $this->clipFor($clipper);

        $response = $this->actingAs($clipper)
            ->get(route('clips.analyze', $clip))
            ->assertSuccessful()
            ->assertJsonStructure([
                'statut', 'url_originale', 'auteur', 'titre_description', 'hashtags',
                'statistiques' => ['vues', 'likes', 'commentaires', 'partages'],
                'duree_secondes', 'date_publication',
            ]);

        $response->assertJson(['statut' => 'success']);
    }

    #[Test]
    public function nobody_analyzes_somebody_elses_clip(): void
    {
        // Même raisonnement que le bouton d'actualisation : sans ce contrôle,
        // n'importe quel clippeur connecté pourrait lire le détail (auteur,
        // légende, statistiques) du clip de quelqu'un d'autre en devinant son
        // identifiant dans l'URL.
        $clip = $this->clipFor($this->clipper());

        $this->actingAs($this->clipper())
            ->get(route('clips.analyze', $clip))
            ->assertForbidden();
    }

    #[Test]
    public function a_clip_whose_account_needs_reconnecting_is_not_pulled(): void
    {
        // Interroger avec un jeton mort ne rapporte que des 401 et consomme le
        // quota qui manquera aux comptes valides.
        $clipper = $this->clipper();
        $clip = $this->clipFor($clipper);

        $clip->socialAccount->forceFill(['needs_reconnect' => true])->save();

        $this->assertFalse(app(ClipSyncService::class)->syncClip($clip->fresh()));
    }

    #[Test]
    public function the_button_is_on_the_clip_page(): void
    {
        $clipper = $this->clipper();
        $clip = $this->clipFor($clipper);

        $this->actingAs($clipper)
            ->get(route('clips.show', $clip))
            ->assertSuccessful()
            ->assertSee('Actualiser les vues');
    }
}
