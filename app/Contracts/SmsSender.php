<?php

namespace App\Contracts;

use App\Exceptions\SmsFailed;

/**
 * Envoi d'un SMS.
 *
 * Même principe que `SocialProvider` : un contrat, un pilote simulé pour le
 * développement, un pilote réel dès que des identifiants existent. Sans cette
 * séparation, aucun test ne peut éprouver le parcours de vérification sans
 * envoyer de vrais messages — et chaque exécution coûterait de l'argent.
 */
interface SmsSender
{
    /**
     * Envoie un message court.
     *
     * @param  string  $to  Numéro au format international (+33…).
     *
     * @throws SmsFailed
     */
    public function send(string $to, string $message): void;

    /** Nom du pilote, pour les journaux et le contrôle avant ouverture. */
    public function name(): string;

    /** Le pilote écrit-il seulement dans les journaux, sans rien envoyer ? */
    public function isSimulated(): bool;
}
