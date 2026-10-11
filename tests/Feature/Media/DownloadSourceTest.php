<?php

namespace Tests\Feature\Media;

use App\Enums\UserRole;
use App\Exceptions\MediaFailed;
use App\Jobs\DownloadSourceClip;
use App\Livewire\ClipDiscovery;
use App\Models\ClipBookmark;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\Downloader;
use App\Services\Media\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Récupération du mp4 d'un clip repéré.
 *
 * Trois choses doivent tenir, et chacune protège autre chose que le confort :
 *
 *  - un clippeur ne touche jamais au fichier d'un autre : le disque est privé,
 *    et le nom des fichiers contient le titre du clip, donc deviner une
 *    adresse ne doit rien donner ;
 *  - le quota est vérifié avant de lancer le travail : le disque est partagé
 *    avec MySQL et les journaux, et un disque plein n'arrête pas l'outil, il
 *    arrête le site ;
 *  - une seule ligne par clip repéré : un clic répété ne doit pas lancer dix
 *    téléchargements du même fichier, chacun comptant dans le quota.
 */
class DownloadSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ], $attributs));
    }

    protected function bookmark(User $user, array $attributs = []): ClipBookmark
    {
        return ClipBookmark::create(array_merge([
            'user_id' => $user->getKey(),
            'source' => 'twitch',
            'external_id' => 'Clip'.fake()->unique()->numberBetween(1, 99999),
            'url' => 'https://clips.twitch.tv/ClipUn',
            'title' => 'Le moment où tout bascule',
            'broadcaster' => 'KAYZ',
            'views' => 12_000,
            'duration_seconds' => 28,
        ], $attributs));
    }

    /** Un téléchargeur qui écrit un faux fichier, sans réseau ni yt-dlp. */
    protected function fauxTelechargeur(int $octets = 2048): void
    {
        $faux = Mockery::mock(Downloader::class);
        $faux->shouldReceive('estDisponible')->andReturn(true);
        $faux->shouldReceive('recuperer')->andReturnUsing(
            function (string $url, string $destination) use ($octets) {
                @mkdir(dirname($destination), 0755, true);
                file_put_contents($destination, str_repeat('x', $octets));

                return $octets;
            },
        );
        $faux->shouldReceive('duree')->andReturn(28);

        $this->app->instance(Downloader::class, $faux);
    }

    #[Test]
    public function a_clip_is_queued_rather_than_downloaded_in_the_request(): void
    {
        /*
         * Un clip met cinq à trente secondes à arriver. Le faire pendant la
         * requête occuperait un processus PHP tout ce temps — quatre
         * clippeurs simultanés et le site ne répond plus.
         */
        Queue::fake();

        $user = $this->clipper();
        $garde = $this->bookmark($user);

        $this->actingAs($user);

        Livewire::test(ClipDiscovery::class)->call('download', $garde->getKey());

        Queue::assertPushed(DownloadSourceClip::class);

        $this->assertDatabaseHas('media_assets', [
            'user_id' => $user->getKey(),
            'clip_bookmark_id' => $garde->getKey(),
            'status' => MediaAsset::EN_ATTENTE,
        ]);
    }

    #[Test]
    public function clicking_twice_does_not_download_twice(): void
    {
        // Chaque téléchargement compte dans le quota : deux copies du même
        // clip le consommeraient deux fois.
        Queue::fake();

        $user = $this->clipper();
        $garde = $this->bookmark($user);

        $this->actingAs($user);

        $composant = Livewire::test(ClipDiscovery::class);
        $composant->call('download', $garde->getKey());
        $composant->call('download', $garde->getKey());

        $this->assertSame(1, MediaAsset::where('clip_bookmark_id', $garde->getKey())->count());
    }

    #[Test]
    public function one_cannot_download_someone_elses_clip(): void
    {
        Queue::fake();

        $maya = $this->clipper();
        $rival = $this->clipper();
        $garde = $this->bookmark($maya);

        $this->actingAs($rival);

        Livewire::test(ClipDiscovery::class)->call('download', $garde->getKey());

        Queue::assertNothingPushed();
        $this->assertSame(0, MediaAsset::count());
    }

    #[Test]
    public function the_job_writes_the_file_and_marks_it_ready(): void
    {
        $this->fauxTelechargeur(octets: 4096);

        $user = $this->clipper();
        $garde = $this->bookmark($user);

        $asset = MediaAsset::create([
            'user_id' => $user->getKey(),
            'kind' => MediaAsset::SOURCE,
            'clip_bookmark_id' => $garde->getKey(),
            'label' => $garde->title,
            'status' => MediaAsset::EN_ATTENTE,
        ]);

        (new DownloadSourceClip($asset))->handle(app(Downloader::class));

        $asset->refresh();

        $this->assertTrue($asset->estPret());
        $this->assertSame(4096, $asset->bytes);
        $this->assertSame(28, $asset->duration_seconds);
        $this->assertNotNull($asset->expires_at, 'Sans date de purge, le fichier reste pour toujours.');

        // Rangé dans l'espace de la personne, et nulle part ailleurs.
        $this->assertStringStartsWith('clippeurs/'.$user->getKey().'/sources/', (string) $asset->path);
        $this->assertTrue(Storage::disk(Workspace::DISQUE)->exists($asset->path));
    }

    #[Test]
    public function the_filename_never_carries_the_title_as_is(): void
    {
        /*
         * Les titres viennent de Twitch : barres obliques, points de suite,
         * n'importe quel alphabet. Un titre comme « ../../.env » n'est pas une
         * hypothèse d'école quand les titres sont saisis par des inconnus.
         */
        $nom = Workspace::nomDeFichier('../../.env ou /etc/passwd ?');

        $this->assertStringNotContainsString('..', $nom);
        $this->assertStringNotContainsString('/', $nom);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+\.mp4$/', $nom);
    }

    #[Test]
    public function a_failed_download_says_why_and_can_be_retried(): void
    {
        // Une ligne bloquée sur « en cours » afficherait une attente qui ne
        // finit jamais ; le message, lui, dit quoi faire.
        $faux = Mockery::mock(Downloader::class);
        $faux->shouldReceive('recuperer')->andThrow(
            MediaFailed::telechargementRefuse('ERROR: 404 does not exist'),
        );
        $this->app->instance(Downloader::class, $faux);

        $user = $this->clipper();
        $garde = $this->bookmark($user);

        $asset = MediaAsset::create([
            'user_id' => $user->getKey(),
            'kind' => MediaAsset::SOURCE,
            'clip_bookmark_id' => $garde->getKey(),
            'label' => $garde->title,
            'status' => MediaAsset::EN_ATTENTE,
        ]);

        (new DownloadSourceClip($asset))->handle(app(Downloader::class));

        $asset->refresh();

        $this->assertTrue($asset->aEchoue());
        $this->assertStringContainsString('supprimé', $asset->error);
        // Le détail technique n'arrive pas à l'écran.
        $this->assertStringNotContainsString('404', $asset->error);

        Queue::fake();

        $this->actingAs($user);
        Livewire::test(ClipDiscovery::class)->call('retryDownload', $asset->getKey());

        $this->assertSame(MediaAsset::EN_ATTENTE, $asset->fresh()->status);
        Queue::assertPushed(DownloadSourceClip::class);
    }

    #[Test]
    public function a_full_quota_stops_the_download_before_it_starts(): void
    {
        /*
         * Vérifié avant de lancer, pas après : un téléchargement qui échoue à
         * l'écriture laisse un fichier tronqué et une ligne en erreur que
         * personne ne comprend. Et le disque est partagé avec MySQL : le
         * remplir n'arrête pas l'outil, il arrête le site.
         */
        config(['clipping.media.quota_mo' => 1]);

        $user = $this->clipper();

        // Un fichier déjà présent qui remplit le quota.
        MediaAsset::create([
            'user_id' => $user->getKey(),
            'kind' => MediaAsset::SOURCE,
            'label' => 'Déjà là',
            'path' => 'clippeurs/'.$user->getKey().'/sources/deja.mp4',
            'bytes' => 1024 * 1024,
            'status' => MediaAsset::PRET,
        ]);

        Queue::fake();

        $garde = $this->bookmark($user);

        $this->actingAs($user);

        Livewire::test(ClipDiscovery::class)
            ->call('download', $garde->getKey())
            ->assertSet('error', 'Votre espace est plein. Supprimez des fichiers avant d’en télécharger d’autres.');

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_file_is_only_served_to_its_owner(): void
    {
        /*
         * Le disque est privé, et c'est ce contrôle qui le rend utile : les
         * noms de fichiers contiennent le titre du clip, donc deviner une
         * adresse ne doit rien donner.
         */
        $this->fauxTelechargeur();

        $maya = $this->clipper();
        $rival = $this->clipper();

        $asset = MediaAsset::create([
            'user_id' => $maya->getKey(),
            'kind' => MediaAsset::SOURCE,
            'label' => 'Le moment',
            'status' => MediaAsset::EN_ATTENTE,
        ]);

        (new DownloadSourceClip($asset))->handle(app(Downloader::class));

        $this->actingAs($maya)
            ->get(route('media.download', $asset))
            ->assertSuccessful();

        $this->actingAs($rival)
            ->get(route('media.download', $asset))
            ->assertNotFound();
    }

    #[Test]
    public function deleting_a_file_frees_the_quota_and_removes_it_from_the_disk(): void
    {
        // La ligne et le fichier partent ensemble : une ligne supprimée seule
        // laisse un octet qui ne compte plus et que rien ne peut retrouver.
        $this->fauxTelechargeur(octets: 8192);

        $user = $this->clipper();

        $asset = MediaAsset::create([
            'user_id' => $user->getKey(),
            'kind' => MediaAsset::SOURCE,
            'label' => 'Le moment',
            'status' => MediaAsset::EN_ATTENTE,
        ]);

        (new DownloadSourceClip($asset))->handle(app(Downloader::class));

        $chemin = $asset->fresh()->path;
        $espace = Workspace::for($user);

        $this->assertSame(8192, $espace->occupeOctets());

        $this->actingAs($user);
        Livewire::test(ClipDiscovery::class)->call('deleteFile', $asset->getKey());

        $this->assertSame(0, Workspace::for($user->fresh())->occupeOctets());
        $this->assertFalse(Storage::disk(Workspace::DISQUE)->exists($chemin));
        $this->assertNull(MediaAsset::find($asset->getKey()));
    }

    #[Test]
    public function the_purge_frees_expired_files(): void
    {
        $this->fauxTelechargeur();

        $user = $this->clipper();

        $vieux = MediaAsset::create([
            'user_id' => $user->getKey(),
            'kind' => MediaAsset::SOURCE,
            'label' => 'Vieux',
            'path' => 'clippeurs/'.$user->getKey().'/sources/vieux.mp4',
            'bytes' => 1024,
            'status' => MediaAsset::PRET,
            'expires_at' => now()->subDay(),
        ]);

        $recent = MediaAsset::create([
            'user_id' => $user->getKey(),
            'kind' => MediaAsset::SOURCE,
            'label' => 'Récent',
            'path' => 'clippeurs/'.$user->getKey().'/sources/recent.mp4',
            'bytes' => 1024,
            'status' => MediaAsset::PRET,
            'expires_at' => now()->addDays(3),
        ]);

        $this->artisan('media:purge')->assertSuccessful();

        $this->assertNull(MediaAsset::find($vieux->getKey()));
        $this->assertNotNull(MediaAsset::find($recent->getKey()));
    }

    #[Test]
    public function the_downloader_refuses_an_url_from_elsewhere(): void
    {
        /*
         * Sans cette liste, l'adresse d'un clip serait un moyen de faire aller
         * notre serveur n'importe où — y compris sur une adresse interne, ce
         * que yt-dlp accepte sans discuter.
         */
        $telechargeur = new Downloader;

        foreach ([
            'http://clips.twitch.tv/ClipUn',
            'https://evil.example.com/clip',
            'https://169.254.169.254/latest/meta-data',
            'file:///etc/passwd',
        ] as $url) {
            try {
                $telechargeur->recuperer($url, sys_get_temp_dir().'/x.mp4', 1024);
                $this->fail('L’URL « '.$url.' » devait être refusée.');
            } catch (MediaFailed $e) {
                /*
                 * Le message exact, et pas seulement « une erreur » : sans
                 * cette précision, le test passait sur un poste sans yt-dlp
                 * pour une raison qui n'a rien à voir — l'outil manquant, et
                 * non l'adresse refusée.
                 */
                $this->assertSame(
                    MediaFailed::urlRefusee()->getMessage(),
                    $e->getMessage(),
                    'L’URL « '.$url.' » doit être refusée pour ce qu’elle est.',
                );
            }
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
