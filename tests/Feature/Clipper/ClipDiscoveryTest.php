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
            ->assertSee('Mode créateur');
    }

    #[Test]
    public function the_tab_leads_to_fetchclip(): void
    {
        /*
         * L'entrée mène à FetchClip, servi tel quel par Nginx depuis son
         * service Node, et non à une page Laravel. Le test porte sur
         * l'adresse brute : la version précédente vérifiait la route de
         * l'ancienne page, qui existe toujours — elle passait donc sans rien
         * garantir du lien réellement affiché.
         */
        $html = $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Mode créateur')
            ->getContent();

        $this->assertStringContainsString('href="/fetchclip"', $html);
    }

    #[Test]
    public function the_mode_takes_the_screen_with_its_own_shell(): void
    {
        /*
         * Un mode prend l'écran : sa propre grille, sa propre feuille de
         * style claire, sa propre barre. Rendre cette page dans le gabarit du
         * site donnerait deux colonnes latérales côte à côte, chacune
         * annonçant une navigation différente.
         *
         * D'où le contrôle sur la coquille elle-même — et sur la porte de
         * sortie, qu'un mode sans issue visible fait chercher à tâtons.
         */
        $html = $this->actingAs($this->clipper())
            ->get(route('discovery.index'))
            ->assertSuccessful()
            ->getContent();

        $this->assertStringContainsString('class="app"', $html);
        $this->assertStringContainsString('class="sidebar"', $html);
        $this->assertStringContainsString('studio', $html, 'La feuille du mode doit être chargée.');

        // La coquille du site n'a rien à faire ici.
        $this->assertStringNotContainsString('app-sidebar', $html);
        $this->assertStringNotContainsString('nav-burger', $html);

        $this->assertStringContainsString('Quitter le mode', $html);
        $this->assertStringContainsString(route('dashboard', absolute: false), $html);
    }

    #[Test]
    public function the_mode_links_nowhere_outside_the_platform(): void
    {
        // Aucun renvoi vers un dépôt, aucun téléchargement : l'outil de
        // montage ne se distribue pas depuis cet écran.
        $this->actingAs($this->clipper())
            ->get(route('discovery.index'))
            ->assertSuccessful()
            ->assertDontSee('github.com', escape: false);
    }

    #[Test]
    public function the_old_address_still_leads_there(): void
    {
        // L'adresse a circulé : un 404 sur un lien partagé coûte plus qu'une
        // ligne de redirection.
        $this->actingAs($this->clipper())
            ->get('/decouverte')
            ->assertRedirect('/mode-createur');
    }

    #[Test]
    public function the_rail_ranks_live_streamers_by_audience(): void
    {
        /*
         * Le point de départ du travail, et la raison d'être du rail : on
         * regarde qui diffuse maintenant, par audience, et on clique. C'est
         * là que les clips se font, parce que c'est là qu'il y a des
         * spectateurs pour les faire.
         *
         * La version précédente proposait à la place les chaînes qui avaient
         * une campagne chez nous — une poignée, là où la plateforme en compte
         * des milliers. Ce n'était pas le même produit.
         */
        $this->actingAs($this->clipper());

        $lives = Livewire::test(ClipDiscovery::class)->viewData('lives');

        $this->assertNotEmpty($lives);

        $audiences = array_map(fn ($l) => $l->viewers, $lives);
        $attendu = $audiences;
        rsort($attendu);

        $this->assertSame($attendu, $audiences, 'Les directs se lisent par audience décroissante.');
    }

    #[Test]
    public function clicking_a_live_streamer_loads_their_clips(): void
    {
        // Un rail dont les lignes ne font rien est décoratif : c'est pour ça
        // qu'il vit dans le composant et non dans le gabarit.
        $this->actingAs($this->clipper());

        $composant = Livewire::test(ClipDiscovery::class);
        $login = $composant->viewData('lives')[0]->login;

        $composant->call('pick', $login)
            ->assertSet('channels', $login)
            ->assertSet('scope', 'mes')
            ->assertSet('searched', true);

        $clips = $composant->get('results');

        $this->assertNotEmpty($clips);

        foreach ($clips as $clip) {
            $this->assertSame($login, $clip['login']);
        }
    }

    #[Test]
    public function the_whole_site_scope_needs_no_channel(): void
    {
        /*
         * « Tout le site » n'est pas un filtre mais une autre opération :
         * Twitch n'expose aucun classement global de clips, il se reconstitue
         * en balayant les plus grosses catégories. Exiger un pseudo pour
         * cette portée n'aurait aucun sens.
         */
        $this->actingAs($this->clipper());

        $composant = Livewire::test(ClipDiscovery::class)
            ->set('channels', '')
            ->call('setScope', 'tout')
            ->call('search');

        $composant->assertSet('error', '');

        $this->assertNotEmpty($composant->get('results'));
    }

    #[Test]
    public function changing_scope_clears_the_previous_results(): void
    {
        // Les deux portées ne rendent pas la même chose : garder les
        // résultats laisserait croire que le basculement n'a rien fait.
        $this->actingAs($this->clipper());

        $composant = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search');

        $this->assertNotEmpty($composant->get('results'));

        $composant->call('setScope', 'tout')
            ->assertSet('results', [])
            ->assertSet('searched', false);
    }

    #[Test]
    public function a_visited_channel_is_remembered(): void
    {
        // On revient toujours aux mêmes chaînes : les retaper à chaque fois
        // est le genre de friction qui fait abandonner un outil.
        $this->actingAs($this->clipper());

        $composant = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search');

        $this->assertContains('kayz', $composant->viewData('favoris'));
    }

    #[Test]
    public function the_screen_survives_a_broken_live_ranking(): void
    {
        /*
         * Le rail est un confort, la recherche est le métier. Sans ce filet,
         * Twitch indisponible rendait la page entière inaccessible — pour un
         * classement dont on peut se passer.
         */
        $faux = $this->createMock(ClipFinder::class);
        $faux->method('liveStreams')->willThrowException(ClipSearchFailed::refused(503, ''));
        $faux->method('search')->willReturn([]);
        $faux->method('isSimulated')->willReturn(false);
        $faux->method('name')->willReturn('test');

        $manager = $this->createMock(ClipFinderManager::class);
        $manager->method('driver')->willReturn($faux);
        $manager->method('isUsable')->willReturn(true);
        $manager->method('isConfigured')->willReturn(true);

        $this->app->instance(ClipFinderManager::class, $manager);

        $this->actingAs($this->clipper())
            ->get(route('discovery.index'))
            ->assertSuccessful()
            ->assertSee('Aucun direct trouvé.');
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
    public function a_filter_never_costs_a_network_call(): void
    {
        /*
         * Le quota Twitch est celui de la plateforme entière. Retrier ou
         * tamiser doit porter sur ce qui a déjà été rapporté : si changer un
         * tri relançait la recherche, dix clics coûteraient dix séries
         * d'appels à tout le monde.
         *
         * D'où le contrôle : `results` ne bouge pas quand les filtres
         * changent, seul l'affichage change.
         */
        $this->actingAs($this->clipper());

        $composant = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search');

        $brut = $composant->get('results');

        $composant->set('sort', 'duree')->set('minViews', 5_000)->set('hideKept', true);

        $this->assertSame($brut, $composant->get('results'));
    }

    #[Test]
    public function the_minimum_views_filter_applies(): void
    {
        $this->actingAs($this->clipper());

        $composant = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search')
            ->set('minViews', 30_000);

        foreach ($composant->viewData('affichables') as $clip) {
            $this->assertGreaterThanOrEqual(30_000, $clip['views']);
        }
    }

    #[Test]
    public function sorting_by_duration_puts_the_longest_first(): void
    {
        $this->actingAs($this->clipper());

        $clips = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->set('onlyShort', false)
            ->call('search')
            ->set('sort', 'duree')
            ->viewData('affichables');

        $durees = array_column($clips, 'duration');
        $attendu = $durees;
        rsort($attendu);

        $this->assertSame($attendu, $durees);
    }

    #[Test]
    public function hiding_the_kept_ones_removes_them_from_the_results(): void
    {
        $this->actingAs($this->clipper());

        $composant = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search');

        $id = $composant->get('results')[0]['id'];

        $composant->call('keep', $id)->set('hideKept', true);

        $this->assertNotContains(
            $id,
            array_column($composant->viewData('affichables'), 'id'),
        );
    }

    #[Test]
    public function keeping_everything_only_takes_what_is_shown(): void
    {
        /*
         * Le clippeur a filtré pour choisir : le bouton doit porter sur ce
         * qu'il voit. Agir sur les soixante résultats bruts lui remplirait la
         * sélection de clips qu'il venait d'écarter.
         */
        $this->actingAs($this->clipper());

        $composant = Livewire::test(ClipDiscovery::class)
            ->set('channels', 'kayz')
            ->call('search')
            ->set('minViews', 40_000);

        $montres = count($composant->viewData('affichables'));
        $trouves = count($composant->get('results'));

        $this->assertGreaterThan(0, $montres);
        $this->assertLessThan($trouves, $montres, 'Le filtre doit écarter quelque chose.');

        $composant->call('keepAll');

        $this->assertSame($montres, ClipBookmark::count());
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
