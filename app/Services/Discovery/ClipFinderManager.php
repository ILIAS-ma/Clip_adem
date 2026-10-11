<?php

namespace App\Services\Discovery;

use App\Contracts\ClipFinder;

/**
 * Choisit le pilote de recherche.
 *
 * Avec des identifiants Twitch, le vrai ; sans, le simulé — mais jamais en
 * production. Là, montrer des clips inventés serait pire qu'un écran vide : le
 * clippeur ouvrirait un lien qui ne mène nulle part, passerait du temps à
 * chercher un moment qui n'existe pas, et conclurait que la plateforme est
 * cassée. Un message clair vaut mieux qu'une liste crédible et fausse.
 */
class ClipFinderManager
{
    public function driver(): ClipFinder
    {
        if ($this->isConfigured()) {
            return new TwitchClipFinder(
                (string) config('services.twitch.client_id'),
                (string) config('services.twitch.client_secret'),
            );
        }

        return new FakeClipFinder;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.twitch.client_id'))
            && filled(config('services.twitch.client_secret'));
    }

    /**
     * L'écran a-t-il quelque chose de vrai à montrer ?
     *
     * Hors production, le pilote simulé suffit : on développe. En production
     * sans clés, l'écran doit le dire plutôt que de faire semblant.
     */
    public function isUsable(): bool
    {
        return $this->isConfigured() || ! app()->isProduction();
    }
}
