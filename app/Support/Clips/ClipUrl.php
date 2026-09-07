<?php

namespace App\Support\Clips;

use App\Enums\Platform;

/**
 * Une URL de clip décomposée en plateforme + identifiant de post.
 *
 * L'identifiant est ce qui rend un clip unique en base : deux URLs différentes
 * pointant vers le même post doivent produire le même `external_post_id`, sinon
 * la contrainte d'unicité ne protège plus des doublons.
 */
final readonly class ClipUrl
{
    public function __construct(
        public Platform $platform,
        public string $externalPostId,
        public string $canonicalUrl,

        // Le pseudo tel qu'écrit dans l'URL, quand la plateforme en porte un
        // (TikTok). Sert à un premier contrôle de propriété, immédiat et sans
        // appel réseau — avant celui, plus fiable mais différé, qui compare
        // l'identifiant API du propriétaire au premier relevé de vues.
        public ?string $handle = null,
    ) {}
}
