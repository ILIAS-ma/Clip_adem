<?php

namespace App\Services\Discovery;

use App\Contracts\ClipFinder;
use App\Exceptions\ClipSearchFailed;
use App\Support\Discovery\ClipSearch;
use App\Support\Discovery\FoundClip;
use App\Support\Discovery\LiveStream;
use DateTimeImmutable;

/**
 * Pilote simulé.
 *
 * Il existe pour la même raison que `FakeSocialProvider` : obtenir des clés
 * Twitch demande d'enregistrer une application et d'activer la double
 * authentification sur le compte, et tout le reste de l'écran — filtres, tri,
 * sélection, rapprochement avec les campagnes — ne doit pas attendre ça.
 *
 * Les résultats sont déterministes : une même chaîne sur une même période rend
 * toujours les mêmes clips, dans le même ordre. Un aléatoire pur rendrait les
 * tests instables, et on ne saurait pas distinguer un tri cassé d'un tirage
 * malheureux.
 */
class FakeClipFinder implements ClipFinder
{
    public function name(): string
    {
        return 'simulé';
    }

    public function isSimulated(): bool
    {
        return true;
    }

    public function search(ClipSearch $criteria): array
    {
        if ($criteria->channels === []) {
            throw ClipSearchFailed::noChannel();
        }

        $clips = [];

        foreach ($criteria->channels as $chaine) {
            $chaine = mb_strtolower(trim($chaine, " \t\n\r\0\x0B@"));

            if ($chaine === '') {
                continue;
            }

            // Autant de clips par chaîne que le vrai pilote en remonterait,
            // pour que l'écran se comporte pareil dans les deux cas.
            $parChaine = (int) ceil($criteria->limit / max(1, count($criteria->channels)));

            for ($i = 0; $i < $parChaine; $i++) {
                $clip = $this->invente($chaine, $i, $criteria);

                if ($criteria->maxDuration > 0 && ! $clip->estCourt($criteria->maxDuration)) {
                    continue;
                }

                $clips[] = $clip;
            }
        }

        usort($clips, fn (FoundClip $a, FoundClip $b) => $b->views <=> $a->views);

        return array_slice($clips, 0, $criteria->limit);
    }

    /**
     * Les clips « de toute la plateforme », en simulé.
     *
     * Tirés de chaînes inventées mais stables : l'écran doit se comporter
     * comme avec le vrai pilote, qui balaie les grosses catégories et rend
     * donc des chaînes qu'on n'a pas nommées.
     */
    public function globalClips(ClipSearch $criteria): array
    {
        $chaines = array_map(fn (LiveStream $s) => $s->login, $this->liveStreams('', 8));

        return $this->search(new ClipSearch(
            channels: $chaines,
            period: $criteria->period,
            limit: $criteria->limit,
            maxDuration: $criteria->maxDuration,
        ));
    }

    /** @return list<LiveStream> */
    public function liveStreams(string $language = 'fr', int $first = 20): array
    {
        $noms = [
            'kayzlive', 'nayra', 'sayamusic', 'lemoment', 'clipfactory',
            'studiovertical', 'ledirect', 'labonneheure', 'minuitpile', 'troisheures',
        ];

        $lives = [];

        foreach (array_slice($noms, 0, min($first, count($noms))) as $rang => $nom) {
            $graine = crc32($nom.'|'.$language);

            $lives[] = new LiveStream(
                login: $nom,
                name: mb_convert_case($nom, MB_CASE_TITLE),
                // Décroissant avec le rang, comme un vrai classement : l'écran
                // doit pouvoir montrer un ordre qui ressemble au vrai.
                viewers: max(120, 38_000 - $rang * 3_600 - (int) ($graine % 900)),
                game: ['Just Chatting', 'League of Legends', 'GTA V', 'Minecraft'][$graine % 4],
                title: 'Direct de démonstration',
                language: $language !== '' ? $language : 'fr',
            );
        }

        return $lives;
    }

    /** @return list<LiveStream> */
    public function searchChannels(string $query, int $first = 8): array
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        return array_values(array_filter(
            $this->liveStreams('', 10),
            fn (LiveStream $s) => str_contains($s->login, $query),
        ));
    }

    protected function invente(string $chaine, int $rang, ClipSearch $criteria): FoundClip
    {
        /*
         * Une graine tirée du nom de la chaîne et du rang : le même couple
         * rend toujours le même clip, sans table ni stockage.
         */
        $graine = crc32($chaine.'|'.$rang.'|'.$criteria->period);

        $titres = [
            'Le moment où tout bascule',
            'Il ne s’y attendait pas',
            'La réaction du chat',
            'Clutch à une vie',
            'Trente secondes de pur chaos',
            'Personne n’a compris ce qui s’est passé',
            'Le silence après la boulette',
            'La remontée impossible',
        ];

        $durees = [12, 18, 24, 31, 42, 55, 58, 72];

        return new FoundClip(
            id: 'demo-'.substr(hash('sha256', $chaine.$rang.$criteria->period), 0, 16),
            // Une adresse qui existe vraiment, celle de la chaîne : un lien
            // mort dans une démonstration donne l'impression d'une panne.
            url: 'https://www.twitch.tv/'.$chaine.'/clips',
            title: $titres[$graine % count($titres)],
            broadcaster: mb_convert_case($chaine, MB_CASE_TITLE),
            broadcasterLogin: $chaine,
            creator: 'clippeur'.(($graine >> 3) % 40),
            game: ['Just Chatting', 'League of Legends', 'GTA V', 'Minecraft'][$graine % 4],
            language: 'fr',
            // Décroissant avec le rang : c'est ce que fait Twitch, et l'écran
            // doit pouvoir montrer un tri qui ressemble au vrai.
            views: max(120, 48_000 - $rang * 1_450 - (int) ($graine % 900)),
            duration: $durees[($graine >> 5) % count($durees)],
            thumbnail: null,
            createdAt: new DateTimeImmutable('-'.(1 + $rang % 20).' days'),
        );
    }
}
