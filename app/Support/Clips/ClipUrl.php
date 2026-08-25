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
    ) {}
}
