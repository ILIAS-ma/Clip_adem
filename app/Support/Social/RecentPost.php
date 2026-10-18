<?php

namespace App\Support\Social;

use Carbon\CarbonInterface;

/** Une publication récente d'un compte lié, telle que proposée au choix du clippeur. */
final readonly class RecentPost
{
    public function __construct(
        public string $externalPostId,
        public ?string $url,
        public ?string $thumbnailUrl,
        public ?string $caption,
        public int $views,
        public ?CarbonInterface $postedAt,
    ) {}

    /** @return array<string, mixed> valeurs simples, pour le cache et Livewire */
    public function toArray(): array
    {
        return [
            'id' => $this->externalPostId,
            'url' => $this->url,
            'thumbnail' => $this->thumbnailUrl,
            'caption' => $this->caption,
            'views' => $this->views,
            'posted_at' => $this->postedAt?->toIso8601String(),
        ];
    }
}
