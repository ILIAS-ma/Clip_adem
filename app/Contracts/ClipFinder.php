<?php

namespace App\Contracts;

use App\Exceptions\ClipSearchFailed;
use App\Support\Discovery\ClipSearch;
use App\Support\Discovery\FoundClip;
use App\Support\Discovery\LiveStream;

/**
 * Recherche de clips chez un diffuseur.
 *
 * Même motif que `SocialProvider` et `SmsSender` : un contrat, un pilote
 * simulé pour le développement, un pilote réel dès que des identifiants
 * existent. Sans cette séparation, aucun test ne peut éprouver l'écran de
 * découverte sans appeler Twitch — et un test qui dépend du réseau échoue un
 * jour pour une raison qui n'a rien à voir avec le code.
 */
interface ClipFinder
{
    /**
     * Les clips des chaînes demandées, triés par vues décroissantes.
     *
     * @return list<FoundClip>
     *
     * @throws ClipSearchFailed
     */
    public function search(ClipSearch $criteria): array;

    /**
     * Les clips les plus vus de toute la plateforme, sans nommer personne.
     *
     * Séparé de `search()` parce que ce n'est pas la même opération : Twitch
     * n'expose aucun classement global de clips, il faut le reconstituer en
     * balayant les plus grosses catégories du moment — dont les clips
     * couvrent toutes les chaînes qui y diffusent.
     *
     * @return list<FoundClip>
     *
     * @throws ClipSearchFailed
     */
    public function globalClips(ClipSearch $criteria): array;

    /**
     * Les streamers en direct, par audience décroissante.
     *
     * @param  string  $language  Code à deux lettres, ou chaîne vide pour le monde entier.
     * @return list<LiveStream>
     *
     * @throws ClipSearchFailed
     */
    public function liveStreams(string $language = 'fr', int $first = 20): array;

    /**
     * Cherche une chaîne par nom affiché.
     *
     * Le pseudo exact diffère souvent du nom qu'on connaît : sans cette
     * recherche, il faudrait le deviner lettre par lettre.
     *
     * @return list<LiveStream>
     *
     * @throws ClipSearchFailed
     */
    public function searchChannels(string $query, int $first = 8): array;

    /** Nom du pilote, pour les journaux et l'écran. */
    public function name(): string;

    /** Le pilote invente-t-il ses résultats ? */
    public function isSimulated(): bool;
}
