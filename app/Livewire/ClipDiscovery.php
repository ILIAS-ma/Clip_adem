<?php

namespace App\Livewire;

use App\Enums\CampaignStatus;
use App\Enums\CreatorKind;
use App\Exceptions\ClipSearchFailed;
use App\Jobs\DownloadSourceClip;
use App\Models\Campaign;
use App\Models\ClipBookmark;
use App\Models\MediaAsset;
use App\Services\Discovery\ClipFinderManager;
use App\Services\Media\Workspace;
use App\Support\Discovery\ClipSearch;
use App\Support\Discovery\FoundClip;
use App\Support\Discovery\LiveStream;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Découverte : trouver le moment à clipper.
 *
 * Porté de l'outil local FetchClip, mais recentré sur ce que le générique ne
 * peut pas faire : rapprocher chaque clip trouvé des campagnes ouvertes ici.
 * Chercher sur tout Twitch donne des clips qui ne rapportent rien ; chercher
 * chez les streamers qui financent une campagne donne des clips payés au
 * millier de vues, et le cachet est affiché avant même le téléchargement.
 *
 * Le montage et le téléchargement restent sur la machine du clippeur. Rendre
 * une vidéo verticale demande à ffmpeg une à deux minutes à plein régime ; le
 * faire ici, sur le serveur qui sert aussi le site, rendrait l'interface
 * inutilisable pour tout le monde dès deux clippeurs simultanés.
 */
class ClipDiscovery extends Component
{
    /** Pseudos de chaînes, séparés par des virgules. */
    #[Url(as: 'chaines', except: '')]
    public string $channels = '';

    #[Url(as: 'periode', except: '7j')]
    public string $period = '7j';

    #[Url(as: 'courts', except: true)]
    public bool $onlyShort = true;

    /*
     * Les filtres ci-dessous ne touchent pas au réseau : ils retrient et
     * tamisent les résultats déjà rapportés. C'est pour cela qu'ils vivent
     * dans une ligne distincte à l'écran — changer un tri ne doit pas
     * dépenser le quota Twitch de toute la plateforme.
     */

    /**
     * « mes » : les clips des chaînes saisies. « tout » : les plus vus de
     * toute la plateforme, sans nommer personne.
     *
     * Ce n'est pas un filtre d'affichage mais deux opérations distinctes :
     * Twitch n'expose aucun classement global de clips, « tout » se
     * reconstitue en balayant les plus grosses catégories du moment.
     */
    #[Url(as: 'portee', except: 'mes')]
    public string $scope = 'mes';

    #[Url(as: 'vuesmin', except: 0)]
    public int $minViews = 0;

    #[Url(as: 'tri', except: 'vues')]
    public string $sort = 'vues';

    #[Url(as: 'masquer', except: false)]
    public bool $hideKept = false;

    /** Résultats de la dernière recherche, pour ne pas la rejouer à chaque rendu. */
    public array $results = [];

    public string $error = '';

    public bool $searched = false;

    /** Identifiant du clip ouvert dans le lecteur, s'il y en a un. */
    public ?string $playing = null;

    /** Le pilote invente-t-il ses résultats ? Passé par la page. */
    public bool $simule = false;

    /** La recherche a-t-elle de quoi fonctionner ? */
    public bool $utilisable = true;

    /** Les tris proposés, et ce sur quoi ils portent. */
    public const TRIS = [
        'vues' => 'Vues',
        'date' => 'Date',
        'duree' => 'Durée',
    ];

    /** Langue des directs du rail. Vide = le monde entier. */
    #[Url(as: 'langue', except: 'fr')]
    public string $liveLanguage = 'fr';

    public function setScope(string $scope): void
    {
        $this->scope = $scope === 'tout' ? 'tout' : 'mes';

        // Changer de portée change l'opération, donc les résultats affichés
        // ne correspondent plus : les garder laisserait croire que le
        // basculement n'a rien fait.
        $this->results = [];
        $this->searched = false;
    }

    /** Charge les clips d'une chaîne depuis le rail, en un clic. */
    public function pick(string $login): void
    {
        $this->channels = $login;
        $this->scope = 'mes';
        $this->search();
    }

    /**
     * Les chaînes récemment explorées.
     *
     * En session plutôt qu'en base : c'est une commodité de navigation, pas
     * une donnée du produit. Une table pour ça demanderait une migration, une
     * purge et une politique de conservation, pour une liste que personne ne
     * réclamera si elle disparaît.
     *
     * @return list<string>
     */
    public function favoris(): array
    {
        return array_values((array) session('decouverte.favoris', []));
    }

    /** Retient une chaîne visitée, les huit dernières. */
    protected function retenir(array $chaines): void
    {
        if ($chaines === []) {
            return;
        }

        $liste = collect($chaines)
            ->merge($this->favoris())
            ->map(fn ($c) => mb_strtolower((string) $c))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        session(['decouverte.favoris' => $liste]);
    }

    public function mount(bool $simule = false, bool $utilisable = true): void
    {
        $this->simule = $simule;
        $this->utilisable = $utilisable;

        /*
         * Aucun pré-remplissage, et plus de couplage aux campagnes.
         *
         * La réponse à « je cherche quoi ? » est le rail des directs : on
         * regarde qui diffuse maintenant, par audience, et on clique. Mettre
         * des noms dans le champ à la place revenait à décider pour le
         * clippeur, et à ne lui montrer qu'une poignée de chaînes là où la
         * plateforme en compte des milliers.
         */
    }

    public function search(): void
    {
        $this->searched = true;
        $this->error = '';
        $this->results = [];

        $finder = app(ClipFinderManager::class);

        if (! $finder->isUsable()) {
            $this->error = ClipSearchFailed::notConfigured()->getMessage();

            return;
        }

        $criteres = new ClipSearch(
            channels: $this->chaines(),
            period: array_key_exists($this->period, ClipSearch::PERIODES) ? $this->period : '7j',
            limit: (int) config('clipping.discovery.limit', 60),
            maxDuration: $this->onlyShort
                ? (int) config('clipping.discovery.max_duration', 60)
                : 0,
        );

        try {
            $pilote = $finder->driver();

            $clips = $this->scope === 'tout'
                ? $pilote->globalClips($criteres)
                : $pilote->search($criteres);
        } catch (ClipSearchFailed $e) {
            $this->error = $e->getMessage();

            return;
        }

        // Une nouvelle recherche referme le lecteur : il montrerait un clip
        // qui n'est plus dans la liste.
        $this->playing = null;

        if ($this->scope === 'mes' && $clips !== []) {
            $this->retenir($criteres->channels);
        }

        $this->results = array_map(fn (FoundClip $clip) => [
            'id' => $clip->id,
            'url' => $clip->url,
            'title' => $clip->title,
            'broadcaster' => $clip->broadcaster,
            'login' => $clip->broadcasterLogin,
            'creator' => $clip->creator,
            'views' => $clip->views,
            'duration' => $clip->duration,
            'duration_label' => $clip->dureeLisible(),
            'posted_at' => $clip->createdAt?->format('Y-m-d H:i:s'),
            'language' => $clip->language,
            'game' => $clip->game,
            'thumbnail' => $clip->thumbnail
                // Twitch sert un gabarit à trous dans ses vignettes récentes.
                ? str_replace(['%{width}', '%{height}'], ['480', '272'], $clip->thumbnail)
                : null,
        ], $clips);
    }

    /**
     * Ouvre le clip dans le lecteur Twitch intégré.
     *
     * Regarder sans quitter la page change la façon de travailler : on passe
     * dix clips en revue d'affilée au lieu d'ouvrir dix onglets, puis de les
     * refermer en ayant perdu la liste.
     */
    public function play(string $id): void
    {
        $this->playing = collect($this->results)->firstWhere('id', $id) ? $id : null;
    }

    public function closePlayer(): void
    {
        $this->playing = null;
    }

    /** Met un clip de côté, pour soi seul. */
    public function keep(string $id): void
    {
        $trouve = collect($this->results)->firstWhere('id', $id);

        if ($trouve === null) {
            return;
        }

        $campagne = $this->campagnesParChaine()->get($trouve['login']);

        ClipBookmark::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'source' => 'twitch',
                'external_id' => $trouve['id'],
            ],
            [
                'url' => $trouve['url'],
                'title' => mb_substr($trouve['title'], 0, 255),
                'broadcaster' => mb_substr($trouve['broadcaster'], 0, 128),
                'views' => $trouve['views'],
                'duration_seconds' => $trouve['duration'],
                'thumbnail_url' => $trouve['thumbnail'],
                'campaign_id' => $campagne?->getKey(),
            ],
        );
    }

    /**
     * Met de côté tout ce qui est affiché.
     *
     * Sur ce qui est affiché, pas sur tout ce qui a été trouvé : le clippeur
     * a filtré pour choisir, le bouton doit porter sur ce qu'il voit. Agir
     * sur les soixante résultats bruts lui remplirait la sélection de clips
     * qu'il venait justement d'écarter.
     */
    public function keepAll(): void
    {
        foreach ($this->affichables() as $clip) {
            $this->keep($clip['id']);
        }
    }

    /**
     * Met un clip en file de téléchargement.
     *
     * En file et non tout de suite : un clip met cinq à trente secondes à
     * arriver, et une requête qui attend ça occupe un processus PHP pendant
     * tout ce temps. Quatre clippeurs simultanés suffiraient à ce que le site
     * ne réponde plus.
     */
    public function download(int $bookmark): void
    {
        $garde = ClipBookmark::forUser(auth()->user())->find($bookmark);

        if ($garde === null) {
            return;
        }

        $espace = Workspace::for(auth()->user());

        if ($espace->resteOctets() <= 0) {
            $this->error = 'Votre espace est plein. Supprimez des fichiers avant d’en télécharger d’autres.';

            return;
        }

        /*
         * Une seule ligne par clip repéré : recliquer ne relance rien tant
         * que le fichier est là ou en route. Sans cette garde, un clic répété
         * ferait autant de téléchargements du même clip, chacun comptant dans
         * le quota.
         */
        $existant = MediaAsset::forUser(auth()->user())
            ->where('clip_bookmark_id', $garde->getKey())
            ->whereIn('status', [MediaAsset::EN_ATTENTE, MediaAsset::EN_COURS, MediaAsset::PRET])
            ->first();

        if ($existant !== null) {
            return;
        }

        $asset = MediaAsset::create([
            'user_id' => auth()->id(),
            'kind' => MediaAsset::SOURCE,
            'clip_bookmark_id' => $garde->getKey(),
            'label' => $garde->title,
            'status' => MediaAsset::EN_ATTENTE,
        ]);

        DownloadSourceClip::dispatch($asset);
    }

    /** Relance un téléchargement qui a échoué. */
    public function retryDownload(int $asset): void
    {
        $ligne = MediaAsset::forUser(auth()->user())
            ->where('kind', MediaAsset::SOURCE)
            ->find($asset);

        if ($ligne === null || ! $ligne->aEchoue()) {
            return;
        }

        $ligne->update(['status' => MediaAsset::EN_ATTENTE, 'error' => null]);

        DownloadSourceClip::dispatch($ligne);
    }

    public function deleteFile(int $asset): void
    {
        MediaAsset::forUser(auth()->user())->find($asset)?->supprimerAvecFichier();
    }

    public function forget(int $bookmark): void
    {
        // Portée par l'utilisateur, jamais par l'identifiant seul : sans ce
        // filtre, une requête forgée supprimerait la sélection d'autrui.
        ClipBookmark::forUser(auth()->user())->whereKey($bookmark)->delete();
    }

    /** Déclare un clip monté et publié : il ne doit plus ressortir. */
    public function markTaken(int $bookmark): void
    {
        ClipBookmark::forUser(auth()->user())
            ->whereKey($bookmark)
            ->update(['taken_at' => now()]);
    }

    public function markFree(int $bookmark): void
    {
        ClipBookmark::forUser(auth()->user())
            ->whereKey($bookmark)
            ->update(['taken_at' => null]);
    }

    /** @return list<string> */
    protected function chaines(): array
    {
        $max = (int) config('clipping.discovery.max_channels', 5);

        return collect(preg_split('/[,\s]+/', $this->channels) ?: [])
            ->map(fn ($c) => mb_strtolower(trim((string) $c, " \t\n\r\0\x0B@")))
            ->filter()
            ->unique()
            // Chaque chaîne coûte une série d'appels : sans plafond, un champ
            // rempli au copier-coller épuiserait le quota de tout le site.
            ->take($max)
            ->values()
            ->all();
    }

    /**
     * Les streamers qui ont une campagne visible, par pseudo de chaîne.
     *
     * @return Collection<string, Campaign>
     */
    protected function campagnesParChaine(): Collection
    {
        return once(fn () => Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->with('creator')
            ->get()
            ->filter(fn (Campaign $c) => $c->creator?->kind() === CreatorKind::Streamer)
            ->flatMap(function (Campaign $campagne) {
                // Une chaîne Twitch et une chaîne Kick peuvent porter la même
                // campagne : les deux pseudos y mènent.
                return collect([
                    $campagne->creator->twitch_handle,
                    $campagne->creator->kick_handle,
                ])
                    ->filter()
                    ->mapWithKeys(fn ($pseudo) => [mb_strtolower($pseudo) => $campagne]);
            }));
    }

    /**
     * Les directs du rail, ou rien si le diffuseur ne répond pas.
     *
     * Une panne du classement ne doit pas emporter l'écran : le rail est un
     * confort, la recherche est le métier. Sans ce filet, Twitch indisponible
     * rendait la page entière inaccessible.
     *
     * @return list<LiveStream>
     */
    protected function lives(): array
    {
        $finder = app(ClipFinderManager::class);

        if (! $finder->isUsable()) {
            return [];
        }

        try {
            return $finder->driver()->liveStreams($this->liveLanguage, 12);
        } catch (ClipSearchFailed) {
            return [];
        }
    }

    /**
     * Les résultats tels qu'ils s'affichent : tamisés et retriés.
     *
     * Jamais en modifiant `$results`, qui garde ce que le diffuseur a
     * réellement rendu : relâcher un filtre doit faire réapparaître les
     * clips, pas obliger à relancer une recherche — et donc à repayer un
     * appel réseau.
     */
    protected function affichables(): array
    {
        $clips = array_filter($this->results, function (array $clip) {
            if ($this->minViews > 0 && $clip['views'] < $this->minViews) {
                return false;
            }

            if ($this->hideKept && in_array($clip['id'], $this->dejaPris(), true)) {
                return false;
            }

            return true;
        });

        $clips = array_values($clips);

        usort($clips, match ($this->sort) {
            'date' => fn ($a, $b) => strcmp((string) $b['posted_at'], (string) $a['posted_at']),
            'duree' => fn ($a, $b) => $b['duration'] <=> $a['duration'],
            default => fn ($a, $b) => $b['views'] <=> $a['views'],
        });

        return $clips;
    }

    /** @return list<string> */
    protected function dejaPris(): array
    {
        return once(fn () => ClipBookmark::forUser(auth()->user())->pluck('external_id')->all());
    }

    public function render()
    {
        $finder = app(ClipFinderManager::class);

        return view('livewire.clip-discovery', [
            'finder' => $finder,
            'periodes' => array_keys(ClipSearch::PERIODES),

            /*
             * Le classement des directs : c'est la réponse à « je cherche
             * quoi ? ». On regarde qui diffuse maintenant, par audience, et
             * on clique — un champ vide renverrait le clippeur sur Twitch.
             *
             * Une panne ici ne doit pas emporter l'écran : le rail est un
             * confort, la recherche est le métier.
             */
            'lives' => $this->lives(),
            'favoris' => $this->favoris(),
            'langues' => ['fr' => 'FR', 'en' => 'EN', 'es' => 'ES', 'de' => 'DE', '' => 'Monde'],

            /*
             * Twitch exige que le domaine parent soit déclaré dans l'URL
             * d'intégration, et qu'il corresponde à celui qui affiche la
             * page — sinon le lecteur refuse de démarrer sans rien dire.
             */
            'parent' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',

            // Les identifiants du pilote simulé ne correspondent à aucun clip
            // réel : proposer un lecteur rendrait un cadre noir.
            'lecturePossible' => ! app(ClipFinderManager::class)->driver()->isSimulated(),

            'selection' => ClipBookmark::forUser(auth()->user())
                ->with(['campaign', 'medias'])
                ->orderByRaw('taken_at is not null')
                ->latest()
                ->get(),

            /*
             * Les fichiers du clippeur, et l'état de son espace. Affichés
             * même vides : c'est là qu'on vient voir si un téléchargement a
             * abouti, et un bloc absent se lit comme une fonctionnalité
             * manquante.
             */
            'fichiers' => MediaAsset::forUser(auth()->user())
                ->sources()
                ->latest()
                ->get(),

            'espace' => Workspace::for(auth()->user()),

            'dejaPris' => $this->dejaPris(),

            // Le nombre total sert à dire ce qu'un filtre a écarté : « 12 sur
            // 60 » se lit, « 12 » laisse croire que la recherche a échoué.
            'affichables' => $this->affichables(),
            'trouves' => count($this->results),
            'tris' => self::TRIS,
        ]);
    }
}
