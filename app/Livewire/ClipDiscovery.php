<?php

namespace App\Livewire;

use App\Enums\CampaignStatus;
use App\Enums\CreatorKind;
use App\Enums\Platform;
use App\Exceptions\ClipSearchFailed;
use App\Models\Campaign;
use App\Models\ClipBookmark;
use App\Services\Discovery\ClipFinderManager;
use App\Support\Discovery\ClipSearch;
use App\Support\Discovery\FoundClip;
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

    /** Résultats de la dernière recherche, pour ne pas la rejouer à chaque rendu. */
    public array $results = [];

    public string $error = '';

    public bool $searched = false;

    public function mount(): void
    {
        /*
         * Pré-remplissage avec les streamers qui ont une campagne ouverte :
         * c'est la réponse à « je cherche quoi ? », qui est la vraie première
         * question. Un champ vide renvoie le clippeur sur Twitch, où rien ne
         * lui dit quelles chaînes rapportent.
         */
        if ($this->channels === '') {
            $this->channels = $this->streamersAvecCampagne()->implode(', ');
        }
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

        try {
            $clips = $finder->driver()->search(new ClipSearch(
                channels: $this->chaines(),
                period: array_key_exists($this->period, ClipSearch::PERIODES) ? $this->period : '7j',
                limit: (int) config('clipping.discovery.limit', 60),
                maxDuration: $this->onlyShort
                    ? (int) config('clipping.discovery.max_duration', 60)
                    : 0,
            ));
        } catch (ClipSearchFailed $e) {
            $this->error = $e->getMessage();

            return;
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
            'thumbnail' => $clip->thumbnail
                // Twitch sert un gabarit à trous dans ses vignettes récentes.
                ? str_replace(['%{width}', '%{height}'], ['480', '272'], $clip->thumbnail)
                : null,
        ], $clips);
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

    /** @return Collection<int, string> */
    protected function streamersAvecCampagne(): Collection
    {
        return $this->campagnesParChaine()->keys()->take(
            (int) config('clipping.discovery.max_channels', 5)
        )->values();
    }

    public function render()
    {
        $campagnes = $this->campagnesParChaine();

        return view('livewire.clip-discovery', [
            'finder' => app(ClipFinderManager::class),
            'periodes' => array_keys(ClipSearch::PERIODES),

            /*
             * Le rapprochement est fait au rendu, pas à la recherche : une
             * campagne peut s'épuiser entre les deux, et afficher un cachet
             * qui n'est plus servi ferait monter un clip pour rien.
             */
            'campagnes' => $campagnes,
            'tiktok' => Platform::TikTok,

            'selection' => ClipBookmark::forUser(auth()->user())
                ->with('campaign')
                ->orderByRaw('taken_at is not null')
                ->latest()
                ->get(),

            'dejaPris' => ClipBookmark::forUser(auth()->user())
                ->pluck('external_id')
                ->all(),
        ]);
    }
}
