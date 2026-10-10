<?php

namespace Tests\Feature\Creator;

use App\Enums\CampaignStatus;
use App\Enums\CreatorKind;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Streamer ou créateur.
 *
 * Les deux financent des campagnes de la même façon ; ce qui change, c'est la
 * matière qu'ils fournissent. Un artiste joint un extrait à sa campagne, un
 * streamer produit des heures de direct dont la source ne bouge pas d'une
 * campagne à l'autre.
 *
 * Ce qui doit tenir : la nature est toujours lisible, elle décide des champs
 * demandés des deux côtés, elle n'efface jamais ceux de l'autre nature, et le
 * clippeur reçoit le lien sans lequel il n'a rien à clipper.
 */
class StreamerKindTest extends TestCase
{
    use RefreshDatabase;

    protected function creatorUser(array $attributs = []): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        Creator::factory()->create(array_merge([
            'user_id' => $user->getKey(),
            'is_active' => true,
        ], $attributs));

        return $user->fresh();
    }

    protected function clipperUser(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'test.clips',
            'country' => 'FR',
            'paypal_email' => 'test@paypal.test',
            'profile_completed_at' => now(),
        ]);
    }

    #[Test]
    public function an_existing_profile_is_an_artist(): void
    {
        /*
         * Les fiches d'avant l'arrivée des streamers n'ont pas de nature
         * déclarée. Le défaut ne doit pas être « indéfini » : leur espace
         * s'afficherait sans libellé jusqu'à ce qu'un administrateur les
         * reprenne une par une.
         */
        $creator = Creator::factory()->create();

        $this->assertSame(CreatorKind::Artist, $creator->fresh()->kind());
        $this->assertFalse($creator->isStreamer());
    }

    #[Test]
    public function a_profile_without_the_column_loaded_still_has_a_kind(): void
    {
        // `kind()` est appelé dans les vues : il ne doit jamais rendre null,
        // même sur une instance construite sans la colonne.
        $this->assertSame(CreatorKind::Artist, (new Creator)->kind());
    }

    #[Test]
    public function the_space_announces_the_right_nature(): void
    {
        $this->actingAs($this->creatorUser(['kind' => CreatorKind::Streamer]))
            ->get(route('creator.dashboard'))
            ->assertSuccessful()
            ->assertSee('Espace streamer');

        $this->actingAs($this->creatorUser(['kind' => CreatorKind::Artist]))
            ->get(route('creator.dashboard'))
            ->assertSuccessful()
            ->assertSee('Espace créateur');
    }

    #[Test]
    public function a_streamer_is_asked_for_his_replays(): void
    {
        /*
         * La différence que la personne voit : les champs demandés. Les deux
         * jeux sont dans le document — le basculement se fait sans renvoi —
         * mais le libellé rendu par le serveur, lui, doit être le bon.
         */
        $this->actingAs($this->creatorUser(['kind' => CreatorKind::Streamer]))
            ->get(route('creator.profile.edit'))
            ->assertSuccessful()
            ->assertSee('Rediffusions (VOD)')
            ->assertSee('>Pseudo de chaîne<', escape: false)
            ->assertSee('Ma fiche streamer');
    }

    #[Test]
    public function an_artist_is_asked_for_his_music(): void
    {
        $this->actingAs($this->creatorUser(['kind' => CreatorKind::Artist]))
            ->get(route('creator.profile.edit'))
            ->assertSuccessful()
            ->assertSee('>Nom de scène<', escape: false)
            ->assertSee('Spotify');
    }

    #[Test]
    public function a_profile_is_created_with_the_declared_nature(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('creator.profile.store'), [
                'kind' => 'streamer',
                'name' => 'Kayz',
                'twitch_handle' => 'kayz',
                'vod_url' => 'https://twitch.tv/kayz/videos',
            ])
            ->assertRedirect(route('creator.dashboard'));

        $creator = $user->fresh()->creator;

        $this->assertTrue($creator->isStreamer());
        $this->assertSame('https://twitch.tv/kayz/videos', $creator->vod_url);
    }

    #[Test]
    public function an_invented_nature_is_refused(): void
    {
        // La colonne est un enum : y laisser entrer n'importe quelle chaîne
        // ferait tomber `kind()->label()` sur une fiche déjà enregistrée.
        $user = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('creator.profile.store'), ['kind' => 'producteur', 'name' => 'Kayz'])
            ->assertSessionHasErrors('kind');

        $this->assertNull($user->fresh()->creator);
    }

    #[Test]
    public function an_absent_nature_keeps_the_one_on_file(): void
    {
        /*
         * La nature est facultative à l'envoi. Un formulaire qui ne la porte
         * pas — ancien cache, intégration partielle — ne doit pas ramener un
         * streamer au rang d'artiste au premier enregistrement, ni afficher
         * une erreur sur un champ que personne ne voit.
         */
        $user = $this->creatorUser(['kind' => CreatorKind::Streamer, 'name' => 'Kayz']);

        $this->actingAs($user)
            ->patch(route('creator.profile.update'), ['name' => 'Kayz', 'bio' => 'Direct tous les soirs.'])
            ->assertRedirect(route('creator.profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->creator->isStreamer());
    }

    #[Test]
    public function changing_nature_does_not_erase_the_other_fields(): void
    {
        /*
         * Un streamer qui sort un morceau reste un streamer, et un créateur
         * qui se met au direct garde ses liens. Effacer silencieusement les
         * champs de l'autre nature serait une perte de données que personne ne
         * remarquerait avant d'aller les chercher.
         */
        $user = $this->creatorUser([
            'kind' => CreatorKind::Artist,
            'name' => 'Nayra',
            'spotify_url' => 'https://open.spotify.com/artist/nayra',
        ]);

        $this->actingAs($user)->patch(route('creator.profile.update'), [
            'kind' => 'streamer',
            'name' => 'Nayra',
            'spotify_url' => 'https://open.spotify.com/artist/nayra',
            'twitch_handle' => 'nayra',
            'vod_url' => 'https://twitch.tv/nayra/videos',
        ])->assertRedirect(route('creator.profile.edit'));

        $creator = $user->fresh()->creator;

        $this->assertTrue($creator->isStreamer());
        $this->assertSame('https://open.spotify.com/artist/nayra', $creator->spotify_url);
    }

    #[Test]
    public function a_clipper_is_told_where_to_find_the_replays(): void
    {
        /*
         * Le cœur du dispositif. Un brief de streamer parle de moments de
         * direct ; sans la source, le clippeur lit une consigne qu'il n'a
         * aucun moyen d'exécuter — et la campagne ne démarre jamais.
         */
        $creator = Creator::factory()->streamer()->create([
            'name' => 'Kayz',
            'vod_url' => 'https://twitch.tv/kayz/videos',
            'twitch_handle' => 'kayz',
        ]);

        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create([
                'creator_id' => $creator->getKey(),
                'status' => CampaignStatus::Active,
            ]);

        $this->actingAs($this->clipperUser())
            ->get(route('campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee('Où trouver la matière')
            ->assertSee('https://twitch.tv/kayz/videos', escape: false);
    }

    #[Test]
    public function an_artist_campaign_shows_no_replay_block(): void
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create([
                'creator_id' => Creator::factory()->create()->getKey(),
                'status' => CampaignStatus::Active,
            ]);

        $this->actingAs($this->clipperUser())
            ->get(route('campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertDontSee('Où trouver la matière');
    }
}
