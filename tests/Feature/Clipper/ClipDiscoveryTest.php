<?php

namespace Tests\Feature\Clipper;

use App\Contracts\ClipFinder;
use App\Enums\CampaignStatus;
use App\Enums\CreatorKind;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\ClipSearchFailed;
use App\Livewire\ClipDiscovery;
use App\Models\Campaign;
use App\Models\ClipBookmark;
use App\Models\Creator;
use App\Models\User;
use App\Services\Discovery\ClipFinderManager;
use App\Services\Discovery\FakeClipFinder;
use App\Services\Discovery\TwitchClipFinder;
use App\Support\Discovery\ClipSearch;
use App\Support\Discovery\FoundClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Découverte : trouver le moment à clipper.
 *
 * Reprise de l'outil local FetchClip, mais recentrée sur ce qu'un outil
 * générique ne peut pas faire : rapprocher chaque clip trouvé des campagnes
 * ouvertes ici, et dire son cachet avant le téléchargement.
 *
 * Trois choses doivent tenir, et chacune défend contre un problème différent :
 * la sélection d'un clippeur ne fuit pas vers les autres, le quota Twitch
 * partagé ne part pas en fumée au premier champ rempli au copier-coller, et un
 * écran sans clés ne montre jamais de clips crédibles mais faux.
 */
class ClipDiscoveryTest extends TestCase
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

    /** Une campagne de streamer, chaîne Twitch comprise. */
    protected function campagneStreamer(string $twitch = 'kayz', int $cachet = 150): Campaign
    {
        $creator = Creator::factory()->streamer()->create([
            'kind' => CreatorKind::Streamer,
            'twitch_handle' => $twitch,
        ]);

        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: $cachet)
            ->funded()
            ->create([
                'creator_id' => $creator->getKey(),
                'status' => CampaignStatus::Active,
            ]);
    }

    #[Test]
    public function the_tab_belongs_to_clippers_only(): void
    {
        /*
         * Un créateur n'a rien à chercher : il paie pour que d'autres le
         * fassent. Un onglet qui lui serait visible donnerait l'impression
         * qu'il doit clipper lui-même.
         */
        $creator = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        Creator::factory()->create(['user_id' => $creator->getKey(), 'is_active' => true]);

        // Renvoyé chez lui, pas refusé : EnsureRole reconduit vers l'espace du
        // rôle plutôt que d'afficher un 403, qui ne dirait pas où aller.
        $this->actingAs($creator)
            ->get(route('discovery.index'))
            ->assertRedirect('/createur');

        $this->actingAs($this->clipper())
            ->get(route('discovery.index'))
            ->assertSuccessful()
            ->assertSee('Trouver le moment à clipper');
    }

    #[Test]
    public function the_tab_appears_in_the_clipper_navigation(): void
    {
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee(route('discovery.index', absolute: false), escape: false)
            ->assertSee('Découverte');
    }

    #[Test]
    public function the_channels_are_prefilled_with_streamers_who_pay(): void
    {
        /*
         * La vraie première question du clippeur est « je cherche quoi ? ».
         * Un champ vide le renvoie sur Twitch, où rien ne lui dit quelles
         * chaînes rapportent.
         */
        $this->campagneStreamer('kayz');

        $this->actingAs($this->clipper());

        Livewire::test(ClipDiscovery::class)
            ->assertSet('channels', 'kayz');
    }

    #[Test]
    public function a_clip_from_a_funded_channel_shows_its_rate(): void
    {
        // C'est tout l'intérêt par rapport à l'outil générique : savoir, avant
        // de télécharger, que ce moment-là est payé et combien.
        $this->campagneStreamer('kayz', cachet: 150);

        $this->actingAs($this->clipper());

        Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search')
            ->assertSet('error', '')
            ->assertSee('1,50 €')
            ->assertDontSee('Aucune campagne ouverte pour cette chaîne');
    }

    #[Test]
    public function a_clip_from_an_unfunded_channel_says_so(): void
    {
        $this->actingAs($this->clipper());

        Livewire::test(ClipDiscovery::class)
            ->set('channels', 'unstreamerquelconque')
            ->call('search')
            ->assertSee('Aucune campagne ouverte pour cette chaîne');
    }

    #[Test]
    public function the_short_filter_excludes_longer_clips(): void
    {
        // Soixante secondes est la limite de TikTok et des Shorts : au-delà il
        // faut couper, donc choisir, donc perdre le moment qui faisait le clip.
        $this->actingAs($this->clipper());

        $courts = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->set('onlyShort', true)
            ->call('search')
            ->get('results');

        $this->assertNotEmpty($courts);

        foreach ($courts as $clip) {
            $this->assertLessThanOrEqual(60, $clip['duration']);
        }

        $tous = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->set('onlyShort', false)
            ->call('search')
            ->get('results');

        $this->assertGreaterThan(count($courts), count($tous));
    }

    #[Test]
    public function results_come_back_most_viewed_first(): void
    {
        $this->actingAs($this->clipper());

        $clips = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz, gotaga')
            ->call('search')
            ->get('results');

        $vues = array_column($clips, 'views');
        $attendu = $vues;
        rsort($attendu);

        $this->assertSame($attendu, $vues);
    }

    #[Test]
    public function a_selection_never_leaks_to_another_clipper(): void
    {
        /*
         * Le défaut de l'outil d'origine, et la raison de la table par
         * utilisateur : il gardait un seul fichier d'historique pour la
         * machine. Mis en ligne tel quel, chacun aurait vu la liste de
         * repérage des autres — dans un système où le budget part au premier
         * arrivé, c'est un avantage qu'aucun règlement ne rattrape.
         */
        $maya = $this->clipper();
        $rival = $this->clipper(['pseudo' => 'rival.clips', 'email' => 'rival@clip.test']);

        $this->actingAs($maya);

        $id = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search')
            ->get('results')[0]['id'];

        Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search')
            ->call('keep', $id);

        $this->assertDatabaseHas('clip_bookmarks', [
            'user_id' => $maya->getKey(),
            'external_id' => $id,
        ]);

        $this->actingAs($rival);

        Livewire::test(ClipDiscovery::class)
            ->assertViewHas('selection', fn ($selection) => $selection->isEmpty());
    }

    #[Test]
    public function one_cannot_remove_someone_elses_selection(): void
    {
        $maya = $this->clipper();
        $rival = $this->clipper(['pseudo' => 'rival.clips', 'email' => 'rival@clip.test']);

        $garde = ClipBookmark::create([
            'user_id' => $maya->getKey(),
            'source' => 'twitch',
            'external_id' => 'abc',
            'url' => 'https://clips.twitch.tv/abc',
            'title' => 'Le moment',
            'broadcaster' => 'KAYZ',
            'views' => 1_000,
            'duration_seconds' => 30,
        ]);

        $this->actingAs($rival);

        Livewire::test(ClipDiscovery::class)
            ->call('forget', $garde->getKey())
            ->call('markTaken', $garde->getKey());

        $this->assertDatabaseHas('clip_bookmarks', [
            'id' => $garde->getKey(),
            'taken_at' => null,
        ]);
    }

    #[Test]
    public function keeping_the_same_clip_twice_does_not_duplicate_it(): void
    {
        $this->actingAs($this->clipper());

        $composant = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search');

        $id = $composant->get('results')[0]['id'];

        $composant->call('keep', $id)->call('keep', $id);

        $this->assertSame(1, ClipBookmark::where('external_id', $id)->count());
    }

    #[Test]
    public function a_published_clip_is_marked_as_taken(): void
    {
        // Ce qui empêche de remonter deux fois le même moment : le deuxième
        // clip ne rapporterait rien et occuperait la modération.
        $maya = $this->clipper();
        $this->actingAs($maya);

        $garde = ClipBookmark::create([
            'user_id' => $maya->getKey(),
            'source' => 'twitch',
            'external_id' => 'abc',
            'url' => 'https://clips.twitch.tv/abc',
            'title' => 'Le moment',
            'broadcaster' => 'KAYZ',
            'views' => 1_000,
            'duration_seconds' => 30,
        ]);

        Livewire::test(ClipDiscovery::class)->call('markTaken', $garde->getKey());

        $this->assertTrue($garde->fresh()->isTaken());

        Livewire::test(ClipDiscovery::class)->call('markFree', $garde->getKey());

        $this->assertFalse($garde->fresh()->isTaken());
    }

    #[Test]
    public function the_number_of_channels_is_capped(): void
    {
        /*
         * Chaque chaîne coûte une série d'appels, et le quota Twitch est celui
         * de la plateforme entière : sans plafond, un champ rempli au
         * copier-coller le dépenserait pour tout le monde.
         */
        $this->actingAs($this->clipper());

        $max = (int) config('clipping.discovery.max_channels');

        $clips = Livewire::test(ClipDiscovery::class)
            ->set('channels', implode(', ', array_map(
                fn ($i) => 'chaine'.$i,
                range(1, $max + 10),
            )))
            ->call('search')
            ->get('results');

        $chaines = array_unique(array_column($clips, 'login'));

        $this->assertLessThanOrEqual($max, count($chaines));
    }

    #[Test]
    public function an_empty_field_asks_for_a_channel(): void
    {
        $this->actingAs($this->clipper());

        Livewire::test(ClipDiscovery::class)
            ->set('channels', '')
            ->call('search')
            ->assertSet('error', ClipSearchFailed::noChannel()->getMessage());
    }

    #[Test]
    public function production_without_keys_shows_nothing_rather_than_fake_clips(): void
    {
        /*
         * Montrer des clips inventés en production serait pire qu'un écran
         * vide : le clippeur ouvrirait un lien qui ne mène nulle part,
         * chercherait un moment qui n'existe pas, et conclurait que la
         * plateforme est cassée.
         */
        config(['services.twitch.client_id' => null, 'services.twitch.client_secret' => null]);
        app()->detectEnvironment(fn () => 'production');

        $this->actingAs($this->clipper());

        Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search')
            ->assertSet('results', [])
            ->assertSet('error', ClipSearchFailed::notConfigured()->getMessage());
    }

    #[Test]
    public function the_fake_driver_only_serves_outside_production(): void
    {
        $manager = app(ClipFinderManager::class);

        config(['services.twitch.client_id' => null, 'services.twitch.client_secret' => null]);

        $this->assertInstanceOf(FakeClipFinder::class, $manager->driver());
        $this->assertTrue($manager->isUsable());

        config([
            'services.twitch.client_id' => 'id-de-test',
            'services.twitch.client_secret' => 'secret-de-test',
        ]);

        $this->assertInstanceOf(TwitchClipFinder::class, $manager->driver());
    }

    #[Test]
    public function the_fake_driver_is_deterministic(): void
    {
        // Sans cela, on ne saurait pas distinguer un tri cassé d'un tirage
        // malheureux, et la suite échouerait un jour sur deux.
        $finder = new FakeClipFinder;
        $criteres = new ClipSearch(channels: ['kayz'], period: '7j');

        $premier = $finder->search($criteres);
        $second = $finder->search($criteres);

        $this->assertSame(
            array_map(fn (FoundClip $c) => [$c->id, $c->views], $premier),
            array_map(fn (FoundClip $c) => [$c->id, $c->views], $second),
        );
    }

    #[Test]
    public function the_twitch_driver_asks_for_a_token_once(): void
    {
        /*
         * Chaque requête PHP est un processus neuf : un jeton gardé en
         * propriété serait redemandé à chaque clic, et Twitch finit par
         * refuser ce rythme. Il est donc en cache partagé.
         */
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'jeton', 'expires_in' => 3600]),
            'api.twitch.tv/helix/users*' => Http::response(['data' => [
                ['id' => '42', 'login' => 'kayz'],
            ]]),
            'api.twitch.tv/helix/clips*' => Http::response(['data' => [
                [
                    'id' => 'ClipUn',
                    'url' => 'https://clips.twitch.tv/ClipUn',
                    'title' => 'Le moment',
                    'broadcaster_name' => 'KAYZ',
                    'creator_name' => 'maya',
                    'view_count' => 12_000,
                    'duration' => 28.5,
                    'created_at' => '2026-10-01T12:00:00Z',
                    'thumbnail_url' => 'https://clips-media.twitch.tv/ClipUn-preview.jpg',
                ],
            ]]),
        ]);

        $finder = new TwitchClipFinder('id-de-test', 'secret-de-test');

        $finder->search(new ClipSearch(channels: ['kayz'], period: '7j'));
        $finder->search(new ClipSearch(channels: ['kayz'], period: '30j'));

        $jetons = collect(Http::recorded())
            ->filter(fn ($paire) => str_contains($paire[0]->url(), 'id.twitch.tv'))
            ->count();

        $this->assertSame(1, $jetons, 'Le jeton doit être réutilisé entre deux recherches.');
    }

    #[Test]
    public function a_decimal_duration_is_rounded_up(): void
    {
        /*
         * Twitch rend « 60.4 ». Arrondi au plus proche, ce clip passerait le
         * filtre des soixante secondes et serait refusé par TikTok au
         * téléversement, après le montage — c'est-à-dire au pire moment.
         */
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'jeton', 'expires_in' => 3600]),
            'api.twitch.tv/helix/users*' => Http::response(['data' => [
                ['id' => '42', 'login' => 'kayz'],
            ]]),
            'api.twitch.tv/helix/clips*' => Http::response(['data' => [
                [
                    'id' => 'ClipLong',
                    'url' => 'https://clips.twitch.tv/ClipLong',
                    'title' => 'Juste trop long',
                    'broadcaster_name' => 'KAYZ',
                    'view_count' => 900,
                    'duration' => 60.4,
                ],
            ]]),
        ]);

        $finder = new TwitchClipFinder('id-de-test', 'secret-de-test');

        $this->assertSame([], $finder->search(new ClipSearch(
            channels: ['kayz'], period: '7j', maxDuration: 60,
        )));
    }

    #[Test]
    public function a_refusal_is_explained_in_plain_words(): void
    {
        // « Twitch a répondu 429 » n'apprend rien au clippeur ; « réessayez
        // dans une minute » lui dit quoi faire.
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'jeton', 'expires_in' => 3600]),
            'api.twitch.tv/*' => Http::response(['message' => 'too many requests'], 429),
        ]);

        $finder = new TwitchClipFinder('id-de-test', 'secret-de-test');

        try {
            $finder->search(new ClipSearch(channels: ['kayz'], period: '7j'));
            $this->fail('La recherche devait échouer.');
        } catch (ClipSearchFailed $e) {
            $this->assertStringContainsString('Réessayez dans une minute', $e->getMessage());
            $this->assertStringNotContainsString('429', $e->getMessage());
            $this->assertTrue($e->isTemporary());
        }
    }

    #[Test]
    public function a_search_failure_does_not_break_the_screen(): void
    {
        // Une panne chez Twitch doit laisser l'écran lisible : la sélection
        // déjà constituée n'a rien à voir avec la recherche.
        $faux = $this->createMock(ClipFinder::class);
        $faux->method('search')->willThrowException(ClipSearchFailed::refused(503, ''));
        $faux->method('isSimulated')->willReturn(false);
        $faux->method('name')->willReturn('test');

        $manager = $this->createMock(ClipFinderManager::class);
        $manager->method('driver')->willReturn($faux);
        $manager->method('isUsable')->willReturn(true);
        $manager->method('isConfigured')->willReturn(true);

        $this->app->instance(ClipFinderManager::class, $manager);

        $this->actingAs($this->clipper());

        Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search')
            ->assertSet('results', [])
            ->assertSee('Ma sélection');
    }
}
