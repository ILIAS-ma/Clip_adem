<?php

namespace App\Support;

/**
 * Ce qu'il advient d'un code de vérification.
 *
 * Le code n'est rendu que si aucun SMS n'est parti : il faut bien que la
 * personne puisse continuer, et l'écran doit alors dire qu'il est simulé
 * plutôt que d'annoncer un message en route. Dès que le prestataire envoie
 * réellement, `codeSimule` vaut null et le code ne quitte plus le serveur.
 *
 * Cette asymétrie est le cœur du dispositif : il n'existe aucun chemin par
 * lequel un code réellement envoyé puisse s'afficher.
 */
class CodeEnvoye
{
    public function __construct(
        public readonly string $numero,
        public readonly ?string $codeSimule = null,
    ) {}

    public function estSimule(): bool
    {
        return $this->codeSimule !== null;
    }
}
