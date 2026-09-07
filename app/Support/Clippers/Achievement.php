<?php

namespace App\Support\Clippers;

/**
 * Un succès, débloqué ou non. Jamais persisté : recalculé à chaque affichage
 * depuis les mêmes données que le reste du tableau de bord, pour ne jamais
 * diverger d'elles — un badge qui mentirait sur ce qui a été réellement
 * accompli vaudrait moins que pas de badge du tout.
 */
final readonly class Achievement
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public bool $unlocked,
    ) {}
}
