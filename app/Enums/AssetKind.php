<?php

namespace App\Enums;

/**
 * Nature d'une pièce jointe au brief.
 *
 * Le type n'est pas déduit du MIME à l'affichage : un même fichier .mp4 peut
 * être « le son à utiliser » ou « un exemple à regarder », et le clippeur doit
 * savoir lequel avant de l'ouvrir.
 */
enum AssetKind: string
{
    case Audio = 'audio';
    case Video = 'video';
    case Image = 'image';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Audio => 'Son',
            self::Video => 'Vidéo',
            self::Image => 'Image',
            self::Document => 'Document',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Audio => 'heroicon-o-musical-note',
            self::Video => 'heroicon-o-film',
            self::Image => 'heroicon-o-photo',
            self::Document => 'heroicon-o-document-text',
        };
    }

    /** Extensions acceptées à l'upload, par type. */
    public function acceptedExtensions(): array
    {
        return match ($this) {
            self::Audio => ['mp3', 'wav', 'm4a', 'aac', 'ogg'],
            self::Video => ['mp4', 'mov', 'webm'],
            self::Image => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            self::Document => ['pdf', 'txt', 'md', 'docx'],
        };
    }

    /** Taille maximale en kilo-octets, alignée sur ce que pèse chaque type. */
    public function maxSizeKb(): int
    {
        return match ($this) {
            self::Audio => 20 * 1024,
            self::Video => 200 * 1024,
            self::Image => 10 * 1024,
            self::Document => 20 * 1024,
        };
    }
}
