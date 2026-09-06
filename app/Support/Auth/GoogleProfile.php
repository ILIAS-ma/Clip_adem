<?php

namespace App\Support\Auth;

/** Ce que Google renvoie sur la personne qui vient de consentir. */
final readonly class GoogleProfile
{
    public function __construct(
        public string $googleId,
        public string $email,
        public bool $emailVerified,
        public ?string $name = null,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $avatarUrl = null,
    ) {}

    /** Nom affichable, quel que soit ce que Google a bien voulu donner. */
    public function displayName(): string
    {
        return $this->name
            ?: trim(($this->givenName ?? '').' '.($this->familyName ?? ''))
            ?: strstr($this->email, '@', true)
            ?: 'Nouveau membre';
    }
}
