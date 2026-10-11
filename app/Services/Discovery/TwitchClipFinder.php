<?php

namespace App\Services\Discovery;

use App\Contracts\ClipFinder;
use App\Exceptions\ClipSearchFailed;
use App\Support\Discovery\ClipSearch;
use App\Support\Discovery\FoundClip;
use App\Support\Discovery\LiveStream;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Les clips d'une chaîne Twitch, par ordre de vues.
 *
 * Porté depuis l'outil local FetchClip. Le flux est celui des identifiants
 * d'application (`client_credentials`) : il suffit à lire les clips publics et
 * ne demande la connexion de personne — ni du clippeur, ni du streamer. Aucun
 * jeton d'utilisateur n'est donc en jeu ici, et c'est ce qui permet à l'écran
 * de fonctionner pour tout le monde avec une seule paire de clés.
 */
class TwitchClipFinder implements ClipFinder
{
    protected const HELIX = 'https://api.twitch.tv/helix';

    protected const OAUTH = 'https://id.twitch.tv/oauth2/token';

    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
    ) {}

    public function name(): string
    {
        return 'twitch';
    }

    public function isSimulated(): bool
    {
        return false;
    }

    public function search(ClipSearch $criteria): array
    {
        if ($criteria->channels === []) {
            throw ClipSearchFailed::noChannel();
        }

        /*
         * Le quota Twitch est partagé par tous les clippeurs du site : deux
         * personnes qui explorent le même streamer la même heure ne doivent
         * pas coûter deux séries d'appels. Les clips les plus vus d'une
         * semaine ne bougent pas d'une minute à l'autre.
         */
        return Cache::remember(
            $criteria->cleCache(),
            now()->addMinutes((int) config('clipping.discovery.cache_minutes', 15)),
            fn () => $this->fetch($criteria),
        );
    }

    /**
     * Les clips les plus vus de toute la plateforme.
     *
     * Twitch n'expose aucun classement global de clips : il faut le
     * reconstituer en balayant les plus grosses catégories du moment, dont
     * les clips couvrent toutes les chaînes qui y diffusent. Huit catégories
     * suffisent — au-delà, on descend dans des jeux dont les clips font cent
     * vues, et chaque catégorie de plus coûte un appel.
     *
     * Mis en cache comme le reste, et c'est ce qui rend l'opération tenable :
     * le balayage est le même pour tous les clippeurs à un instant donné, donc
     * huit appels par quart d'heure pour le site entier.
     */
    public function globalClips(ClipSearch $criteria): array
    {
        return Cache::remember(
            'decouverte:global:'.md5($criteria->period.'|'.$criteria->limit.'|'.$criteria->maxDuration),
            now()->addMinutes((int) config('clipping.discovery.cache_minutes', 15)),
            fn () => $this->balayerLesCategories($criteria),
        );
    }

    /** @return list<LiveStream> */
    public function liveStreams(string $language = 'fr', int $first = 20): array
    {
        /*
         * Cache court : une audience de direct bouge en permanence, et un
         * classement d'il y a un quart d'heure met en tête quelqu'un qui a
         * coupé. Deux minutes suffisent à absorber la rafale de clippeurs qui
         * ouvrent l'écran en même temps.
         */
        return Cache::remember(
            'decouverte:lives:'.$language.':'.$first,
            now()->addMinutes(2),
            function () use ($language, $first) {
                $reponse = $this->helix('/streams', array_filter([
                    'language' => $language !== '' ? $language : null,
                    'first' => min(100, max(1, $first)),
                    'type' => 'live',
                ]));

                return array_map(fn (array $s) => new LiveStream(
                    login: (string) ($s['user_login'] ?? ''),
                    name: (string) ($s['user_name'] ?? $s['user_login'] ?? ''),
                    viewers: (int) ($s['viewer_count'] ?? 0),
                    game: $s['game_name'] ?? null,
                    title: $s['title'] ?? null,
                    language: $s['language'] ?? null,
                ), $reponse['data'] ?? []);
            },
        );
    }

    /** @return list<LiveStream> */
    public function searchChannels(string $query, int $first = 8): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        return Cache::remember(
            'decouverte:chaines:'.md5(mb_strtolower($query)).':'.$first,
            now()->addMinutes(10),
            function () use ($query, $first) {
                $reponse = $this->helix('/search/channels', [
                    'query' => $query,
                    'first' => min(20, max(1, $first)),
                ]);

                return array_map(fn (array $c) => new LiveStream(
                    login: (string) ($c['broadcaster_login'] ?? ''),
                    name: (string) ($c['display_name'] ?? ''),
                    // La recherche ne donne pas d'audience : seulement si la
                    // chaîne est en direct. Zéro vaut « on ne sait pas ».
                    viewers: 0,
                    game: $c['game_name'] ?? null,
                    title: $c['title'] ?? null,
                    language: $c['broadcaster_language'] ?? null,
                ), $reponse['data'] ?? []);
            },
        );
    }

    /**
     * Le balayage des grosses catégories.
     *
     * @return list<FoundClip>
     */
    protected function balayerLesCategories(ClipSearch $criteria): array
    {
        $jeux = $this->helix('/games/top', ['first' => 8])['data'] ?? [];

        if ($jeux === []) {
            return [];
        }

        $parJeu = (int) ceil($criteria->limit / count($jeux));
        $clips = [];

        foreach ($jeux as $jeu) {
            foreach ($this->clipsPage(['game_id' => (string) $jeu['id']], $criteria, $parJeu) as $brut) {
                $clip = $this->toFoundClip(
                    $brut,
                    mb_strtolower((string) ($brut['broadcaster_name'] ?? '')),
                    $jeu['name'] ?? null,
                );

                if ($criteria->maxDuration > 0 && ! $clip->estCourt($criteria->maxDuration)) {
                    continue;
                }

                $clips[$clip->id] = $clip;
            }
        }

        $clips = array_values($clips);

        usort($clips, fn (FoundClip $a, FoundClip $b) => $b->views <=> $a->views);

        return array_slice($clips, 0, $criteria->limit);
    }

    /** @return list<FoundClip> */
    protected function fetch(ClipSearch $criteria): array
    {
        $users = $this->users($criteria->channels);

        if ($users === []) {
            return [];
        }

        /*
         * Même profondeur par chaîne, pas un quota global : avec deux
         * streamers dont l'un est dix fois plus suivi, un budget commun ne
         * remonterait que les clips du gros et le petit disparaîtrait de
         * l'écran, alors que c'est souvent chez lui que la campagne est encore
         * ouverte.
         */
        $parChaine = (int) ceil($criteria->limit / count($users));

        $clips = [];

        foreach ($users as $user) {
            foreach ($this->clipsPage(['broadcaster_id' => $user['id']], $criteria, $parChaine) as $brut) {
                $clip = $this->toFoundClip($brut, $user['login']);

                if ($criteria->maxDuration > 0 && ! $clip->estCourt($criteria->maxDuration)) {
                    continue;
                }

                // Clé par identifiant : un même clip peut remonter deux fois
                // quand on pagine pendant qu'une vue est comptée.
                $clips[$clip->id] = $clip;
            }
        }

        $clips = array_values($clips);

        usort($clips, fn (FoundClip $a, FoundClip $b) => $b->views <=> $a->views);

        return array_slice($clips, 0, $criteria->limit);
    }

    /**
     * Identifiants des chaînes demandées.
     *
     * @param  list<string>  $logins
     * @return list<array{id: string, login: string}>
     */
    protected function users(array $logins): array
    {
        // Twitch n'accepte pas plus de cent pseudos par appel, et nous n'en
        // proposons jamais autant : la coupe est une ceinture, pas une règle
        // produit.
        $propres = array_slice(array_values(array_filter(array_map(
            fn (string $l) => mb_strtolower(trim($l, " \t\n\r\0\x0B@")),
            $logins,
        ))), 0, 100);

        if ($propres === []) {
            return [];
        }

        $reponse = $this->helix('/users', ['login' => $propres]);

        return array_map(
            fn (array $u) => ['id' => (string) $u['id'], 'login' => (string) $u['login']],
            $reponse['data'] ?? [],
        );
    }

    /**
     * Une page de clips, d'une chaîne ou d'une catégorie.
     *
     * La cible est passée telle quelle — `broadcaster_id` ou `game_id` — parce
     * que les deux recherches ne diffèrent que par ce paramètre. Les dupliquer
     * aurait fait deux méthodes qui divergent dès la première correction de
     * pagination.
     *
     * Twitch rend cent clips au plus par page et les trie déjà par vues.
     *
     * @param  array<string, string>  $cible
     * @return list<array<string, mixed>>
     */
    protected function clipsPage(array $cible, ClipSearch $criteria, int $limite): array
    {
        $clips = [];
        $curseur = null;
        $pages = 0;

        $debut = $criteria->debut();

        while (count($clips) < $limite && $pages < 5) {
            $reponse = $this->helix('/clips', array_filter([
                ...$cible,
                'started_at' => $debut?->toIso8601ZuluString(),
                'ended_at' => $debut ? $criteria->fin()->toIso8601ZuluString() : null,
                'first' => min(100, $limite - count($clips)),
                'after' => $curseur,
            ], fn ($v) => $v !== null && $v !== ''));

            $page = $reponse['data'] ?? [];
            $clips = [...$clips, ...$page];
            $pages++;

            $curseur = $reponse['pagination']['cursor'] ?? null;

            if ($curseur === null || $page === []) {
                break;
            }
        }

        return $clips;
    }

    /** @param  array<string, mixed>  $brut */
    protected function toFoundClip(array $brut, string $login, ?string $jeu = null): FoundClip
    {
        return new FoundClip(
            id: (string) ($brut['id'] ?? ''),
            url: (string) ($brut['url'] ?? ''),
            title: (string) ($brut['title'] ?? 'Sans titre'),
            broadcaster: (string) ($brut['broadcaster_name'] ?? $login),
            broadcasterLogin: $login,
            creator: $brut['creator_name'] ?? null,
            game: $jeu,
            language: $brut['language'] ?? null,
            views: (int) ($brut['view_count'] ?? 0),
            // Twitch rend une durée décimale (« 28.5 ») : on arrondit au
            // supérieur, parce qu'un clip de 60,4 s ne tient pas dans un
            // format qui en accepte 60.
            duration: (int) ceil((float) ($brut['duration'] ?? 0)),
            thumbnail: $brut['thumbnail_url'] ?? null,
            createdAt: isset($brut['created_at'])
                ? new DateTimeImmutable((string) $brut['created_at'])
                : null,
        );
    }

    /**
     * Un appel à l'API Helix.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function helix(string $endpoint, array $params = []): array
    {
        try {
            $reponse = Http::withHeaders([
                'Client-Id' => $this->clientId,
                'Authorization' => 'Bearer '.$this->token(),
            ])->timeout(12)->get(self::HELIX.$endpoint, $params);
        } catch (ConnectionException $e) {
            throw ClipSearchFailed::refused(503, $e->getMessage());
        }

        if ($reponse->status() === 401) {
            // Jeton révoqué côté Twitch : l'oublier force un renouvellement au
            // prochain appel, sinon tous les suivants échoueraient de la même
            // façon jusqu'à l'expiration du cache.
            Cache::forget($this->cleJeton());
        }

        $this->refuseSiErreur($reponse, $endpoint);

        return $reponse->json() ?? [];
    }

    protected function refuseSiErreur(Response $reponse, string $endpoint): void
    {
        if ($reponse->successful()) {
            return;
        }

        $echec = ClipSearchFailed::refused($reponse->status(), $reponse->body());

        Log::warning('Twitch '.$endpoint.' a refusé la recherche', [
            'status' => $reponse->status(),
            'detail' => $echec->detail,
        ]);

        throw $echec;
    }

    /**
     * Jeton d'application, mis en cache jusqu'à peu avant son expiration.
     *
     * En cache partagé plutôt qu'en mémoire du processus : chaque requête PHP
     * est un processus neuf, et un jeton gardé en propriété serait redemandé à
     * chaque clic — Twitch finit par refuser ce rythme.
     */
    protected function token(): string
    {
        $cle = $this->cleJeton();

        if ($jeton = Cache::get($cle)) {
            return (string) $jeton;
        }

        try {
            $reponse = Http::asForm()->timeout(12)->post(self::OAUTH, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ]);
        } catch (ConnectionException $e) {
            throw ClipSearchFailed::refused(503, $e->getMessage());
        }

        if (! $reponse->successful()) {
            throw ClipSearchFailed::refused($reponse->status(), $reponse->body());
        }

        $jeton = (string) ($reponse->json('access_token') ?? '');

        if ($jeton === '') {
            throw ClipSearchFailed::refused(500, 'Réponse Twitch sans jeton.');
        }

        // Une minute de marge : un jeton qui expire entre l'obtention et
        // l'appel produit un 401 que rien n'explique dans les journaux.
        $secondes = max(60, (int) ($reponse->json('expires_in') ?? 3600) - 60);

        Cache::put($cle, $jeton, now()->addSeconds($secondes));

        return $jeton;
    }

    /** La clé dépend du client : changer de clés ne doit pas réutiliser l'ancien jeton. */
    protected function cleJeton(): string
    {
        return 'twitch:app-token:'.substr(hash('sha256', $this->clientId), 0, 16);
    }
}
