<?php

namespace App\Contracts;

use App\Exceptions\ClipSearchFailed;
use App\Support\Discovery\ClipSearch;
use App\Support\Discovery\FoundClip;

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
     * @return list<FoundClip> Triés par vues décroissantes.
     *
     * @throws ClipSearchFailed
     */
    public function search(ClipSearch $criteria): array;

    /** Nom du pilote, pour les journaux et l'écran. */
    public function name(): string;

    /** Le pilote invente-t-il ses résultats ? */
    public function isSimulated(): bool;
}
