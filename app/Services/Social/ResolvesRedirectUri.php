<?php

namespace App\Services\Social;

/**
 * L'adresse de retour OAuth, résolue au même endroit pour les deux appels.
 *
 * Deux raisons de ne pas se contenter de `route()` :
 *
 *  - Le fournisseur compare l'URL **caractère par caractère** avec celle
 *    enregistrée dans la console développeur. Or `route()` la déduit de l'hôte
 *    de la requête : derrière un tunnel de développement ou un répartiteur de
 *    charge, le schéma retombe en `http` et l'URL ne correspond plus. C'est la
 *    première cause d'un « invalid redirect_uri » qu'on met une heure à
 *    comprendre. Une valeur figée en configuration coupe court.
 *  - OAuth exige la **même** adresse à l'autorisation et à l'échange du code.
 *    Les faire calculer par deux appels séparés, c'est laisser une divergence
 *    s'installer le jour où l'un des deux change.
 *
 * Sans variable d'environnement, on retombe sur la route : le développement
 * simulé continue de marcher sans configuration.
 */
trait ResolvesRedirectUri
{
    protected function redirectUri(): string
    {
        // La valeur de l'enum est aussi la clé de configuration : « tiktok »,
        // « youtube », « instagram ».
        $configured = config('services.'.$this->platform()->value.'.redirect');

        return $configured ?: route('social.callback', ['platform' => $this->platform()->value]);
    }
}
