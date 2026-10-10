<?php

namespace App\Enums;

/**
 * Nature d'une fiche créateur.
 *
 * Un artiste et un streamer financent des campagnes de la même façon, mais ils
 * ne fournissent pas la même matière et ne se jugent pas sur les mêmes
 * chiffres. L'artiste donne un extrait de morceau et veut des écoutes ; le
 * streamer donne des rediffusions et veut que ses moments circulent.
 *
 * Deux rôles séparés auraient dupliqué tout l'espace créateur — tableau de
 * bord, suivi de campagne, profil — pour une différence qui ne tient qu'aux
 * champs affichés. D'où une seule nature portée par la fiche : l'administrateur
 * la choisit à la création, et elle décide des libellés et des champs des deux
 * côtés.
 */
enum CreatorKind: string
{
    case Artist = 'artist';
    case Streamer = 'streamer';

    public function label(): string
    {
        return match ($this) {
            self::Artist => 'Créateur',
            self::Streamer => 'Streamer',
        };
    }

    /** En-tête de son propre espace. */
    public function spaceLabel(): string
    {
        return match ($this) {
            self::Artist => 'Espace créateur',
            self::Streamer => 'Espace streamer',
        };
    }

    /** Un streamer n'a pas de « nom de scène », il a un pseudo de chaîne. */
    public function nameLabel(): string
    {
        return match ($this) {
            self::Artist => 'Nom de scène',
            self::Streamer => 'Pseudo de chaîne',
        };
    }

    /**
     * Liens propres à la nature, en plus des réseaux que les deux partagent.
     *
     * @return array<string, array{label: string, placeholder: string, url: bool}>
     */
    public function ownLinks(): array
    {
        return match ($this) {
            self::Artist => [
                'spotify_url' => [
                    'label' => 'Spotify',
                    'placeholder' => 'https://open.spotify.com/…',
                    'url' => true,
                ],
            ],
            self::Streamer => [
                'twitch_handle' => [
                    'label' => 'Twitch',
                    'placeholder' => 'votrechaine',
                    'url' => false,
                ],
                'kick_handle' => [
                    'label' => 'Kick',
                    'placeholder' => 'votrechaine',
                    'url' => false,
                ],
                /*
                 * Le champ qui compte vraiment : sans accès aux rediffusions,
                 * un clippeur n'a rien à clipper. Un artiste joint son extrait
                 * à la campagne ; un streamer, lui, produit des heures de
                 * direct dont la source est la même d'une campagne à l'autre.
                 */
                'vod_url' => [
                    'label' => 'Rediffusions (VOD)',
                    'placeholder' => 'https://twitch.tv/votrechaine/videos',
                    'url' => true,
                ],
            ],
        };
    }

    /** Toutes les colonnes de liens propres aux natures, toutes natures confondues. */
    public static function allOwnLinkFields(): array
    {
        return collect(self::cases())
            ->flatMap(fn (self $kind) => array_keys($kind->ownLinks()))
            ->unique()
            ->values()
            ->all();
    }

    /** Options pour un sélecteur. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $kind) => [$kind->value => $kind->label()])
            ->all();
    }
}
