<?php

namespace App\Support\Discovery;

/**
 * Un clip trouvé chez un diffuseur, avant toute décision.
 *
 * Objet de lecture seule, et volontairement détaché de nos modèles : rien de
 * ce qui vient d'une API tierce n'entre en base par ce chemin. Un clip trouvé
 * n'est qu'une piste — il ne devient une ligne chez nous que si un clippeur le
 * met de côté, et c'est alors nous qui décidons des colonnes.
 */
class FoundClip
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $title,
        public readonly string $broadcaster,
        public readonly string $broadcasterLogin,
        public readonly ?string $creator,
        public readonly ?string $game,
        /** Code de langue de la diffusion, pour le badge de la vignette. */
        public readonly ?string $language,
        public readonly int $views,
        /** Durée en secondes, telle que la donne la plateforme. */
        public readonly int $duration,
        public readonly ?string $thumbnail,
        public readonly ?\DateTimeImmutable $createdAt,
    ) {}

    /** Durée lisible : « 0:42 » plutôt que « 42 ». */
    public function dureeLisible(): string
    {
        return sprintf('%d:%02d', intdiv($this->duration, 60), $this->duration % 60);
    }

    /**
     * Le clip tient-il dans un format vertical court ?
     *
     * Soixante secondes est la limite qui compte : c'est celle de TikTok et des
     * Shorts, et au-delà il faut couper — donc choisir, donc perdre le moment
     * qui faisait le clip.
     */
    public function estCourt(int $secondes = 60): bool
    {
        return $this->duration <= $secondes;
    }

    /** @return array<string, mixed> Ce qu'on garde quand un clippeur le met de côté. */
    public function pourMemoire(): array
    {
        return [
            'external_id' => $this->id,
            'url' => $this->url,
            'title' => mb_substr($this->title, 0, 255),
            'broadcaster' => mb_substr($this->broadcaster, 0, 128),
            'views' => $this->views,
            'duration_seconds' => $this->duration,
            'thumbnail_url' => $this->thumbnail ? mb_substr($this->thumbnail, 0, 512) : null,
        ];
    }
}
