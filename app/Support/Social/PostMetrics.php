<?php

namespace App\Support\Social;

use Carbon\CarbonInterface;

/**
 * Relevé d'une publication à un instant donné.
 *
 * La légende et la durée servent au contrôle de conformité ; les vues servent
 * au crédit. Le compte propriétaire permet de vérifier qu'un clippeur ne
 * soumet pas la vidéo de quelqu'un d'autre.
 */
final readonly class PostMetrics
{
    public function __construct(
        public string $externalPostId,
        public int $views,
        public ?string $caption = null,
        public ?int $durationSeconds = null,
        public ?CarbonInterface $postedAt = null,
        public ?string $ownerExternalId = null,
    ) {}
}
