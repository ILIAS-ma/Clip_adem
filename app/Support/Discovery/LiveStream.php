<?php

namespace App\Support\Discovery;

/**
 * Un streamer en direct, maintenant.
 *
 * C'est le point de départ du travail d'un clippeur : on regarde qui est en
 * live, on va voir, on prend le moment. Le classement par audience n'est pas
 * décoratif — c'est là que les clips se font, parce que c'est là qu'il y a des
 * spectateurs pour les faire.
 */
class LiveStream
{
    public function __construct(
        public readonly string $login,
        public readonly string $name,
        public readonly int $viewers,
        public readonly ?string $game,
        public readonly ?string $title,
        public readonly ?string $language = null,
    ) {}

    /** Audience lisible : « 42 k » plutôt que 42 318. */
    public function audienceLisible(): string
    {
        if ($this->viewers >= 1_000_000) {
            return number_format($this->viewers / 1_000_000, 1, ',', ' ').' M';
        }

        if ($this->viewers >= 1_000) {
            return number_format($this->viewers / 1_000, 0, ',', ' ').' k';
        }

        return (string) $this->viewers;
    }
}
