<?php

namespace App\Support\Discovery;

use Carbon\CarbonImmutable;

/**
 * Ce qu'on cherche.
 *
 * Les critères sont réunis ici plutôt que passés un par un : la liste s'est
 * déjà allongée une fois, et un argument ajouté au milieu d'une signature est
 * une erreur qu'aucun test ne voit si les deux voisins ont le même type.
 */
class ClipSearch
{
    /** Les périodes proposées, et ce qu'elles valent en jours. */
    public const PERIODES = [
        '24h' => 1,
        '7j' => 7,
        '30j' => 30,
        '90j' => 90,
        // Sans borne : Twitch rend alors les clips les plus vus de toute
        // l'histoire de la chaîne, ce qui est souvent ce qu'on cherche sur
        // une chaîne qu'on découvre.
        'tout' => null,
    ];

    /**
     * @param  list<string>  $channels  Pseudos de chaînes, en minuscules.
     */
    public function __construct(
        public readonly array $channels,
        public readonly string $period = '7j',
        public readonly int $limit = 60,
        /** 0 = pas de limite de durée. */
        public readonly int $maxDuration = 60,

        /** Identifiant de catégorie Twitch, ou null. */
        public readonly ?string $gameId = null,

        /** Code de langue à deux lettres, ou chaîne vide pour toutes. */
        public readonly string $language = '',

        /** Vues minimum, appliquées par le diffuseur quand c'est possible. */
        public readonly int $minViews = 0,
    ) {}

    /** Début de la fenêtre, ou null pour « depuis toujours ». */
    public function debut(): ?CarbonImmutable
    {
        $jours = self::PERIODES[$this->period] ?? null;

        return $jours === null ? null : CarbonImmutable::now()->subDays($jours);
    }

    public function fin(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    /**
     * Clé de cache.
     *
     * Les clips les plus vus d'une semaine ne changent pas d'une minute à
     * l'autre, et le quota Twitch est partagé par tous les clippeurs : deux
     * personnes qui cherchent le même streamer ne doivent pas coûter deux
     * appels. La fenêtre est arrondie à l'heure pour que la clé soit stable.
     */
    public function cleCache(): string
    {
        return 'decouverte:'.md5(implode('|', [
            implode(',', $this->channels),
            $this->period,
            $this->limit,
            $this->maxDuration,
            $this->gameId ?? '',
            $this->language,
            CarbonImmutable::now()->format('Y-m-d-H'),
        ]));
    }
}
