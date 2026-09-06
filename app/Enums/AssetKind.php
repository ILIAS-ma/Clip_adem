<?php

namespace App\Enums;

/**
 * Nature d'une pièce jointe au brief.
 *
 * Le type n'est pas déduit du MIME à l'affichage : un même .mp4 peut être « le
 * son à utiliser » ou « un exemple à regarder », et le clippeur doit savoir
 * lequel avant de l'ouvrir.
 *
 * C'est aussi la source unique des extensions acceptées, de leur traduction en
 * types MIME et du poids maximum. Deux listes finiraient par diverger, et le
 * formulaire accepterait alors un fichier que la validation refuse.
 */
enum AssetKind: string
{
    case Audio = 'audio';
    case Video = 'video';
    case Image = 'image';
    case Document = 'document';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Audio => 'Son',
            self::Video => 'Vidéo',
            self::Image => 'Image',
            self::Document => 'Document',
            self::Archive => 'Archive',
        };
    }

    /** Ce que le type sert concrètement, montré sous le sélecteur. */
    public function hint(): string
    {
        return match ($this) {
            self::Audio => 'Le son imposé, une voix off, une nappe.',
            self::Video => 'Rushes, clip officiel, exemple à imiter.',
            self::Image => 'Pochette, logo, capture, planche d’ambiance.',
            self::Document => 'Charte, paroles, script, tableau de consignes.',
            self::Archive => 'Un pack complet en un seul fichier.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Audio => 'heroicon-o-musical-note',
            self::Video => 'heroicon-o-film',
            self::Image => 'heroicon-o-photo',
            self::Document => 'heroicon-o-document-text',
            self::Archive => 'heroicon-o-archive-box',
        };
    }

    /**
     * Extensions acceptées à l'upload.
     *
     * Volontairement larges : un administrateur qui ne peut pas déposer le
     * fichier qu'il a sous la main le met sur un Drive, et le brief perd son
     * intérêt. Le SVG est la seule exclusion délibérée — servi depuis notre
     * propre domaine, il peut embarquer du script et s'exécuter dans le
     * contexte du site.
     *
     * @return list<string>
     */
    public function acceptedExtensions(): array
    {
        return match ($this) {
            self::Audio => ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'flac', 'aif', 'aiff', 'wma'],
            self::Video => ['mp4', 'm4v', 'mov', 'webm', 'avi', 'mkv', 'wmv', 'mpeg', 'mpg', '3gp'],
            self::Image => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp', 'tif', 'tiff', 'heic', 'heif'],
            self::Document => [
                'pdf', 'txt', 'md', 'rtf', 'csv',
                'doc', 'docx', 'odt',
                'xls', 'xlsx', 'ods',
                'ppt', 'pptx', 'odp',
                'srt', 'vtt',
            ],
            self::Archive => ['zip', 'rar', '7z', 'tar', 'gz'],
        };
    }

    /**
     * Types MIME correspondants, pour l'attribut `accept` du sélecteur.
     *
     * Une extension sans MIME connu retombe sur `application/octet-stream` :
     * mieux vaut un filtre approximatif qu'un fichier légitime refusé par le
     * navigateur avant même d'atteindre le serveur.
     *
     * @return list<string>
     */
    public function acceptedMimeTypes(): array
    {
        $mimes = array_map(
            fn (string $extension) => self::MIME_BY_EXTENSION[$extension] ?? 'application/octet-stream',
            $this->acceptedExtensions(),
        );

        return array_values(array_unique($mimes));
    }

    /** Poids maximum en kilo-octets, à la mesure de ce que pèse chaque type. */
    public function maxSizeKb(): int
    {
        return match ($this) {
            self::Audio => 50 * 1024,      // 50 Mo
            self::Video => 500 * 1024,     // 500 Mo
            self::Image => 25 * 1024,      // 25 Mo
            self::Document => 50 * 1024,   // 50 Mo
            self::Archive => 500 * 1024,   // 500 Mo
        };
    }

    /** Le même plafond, en méga-octets, pour l'afficher. */
    public function maxSizeMb(): int
    {
        return intdiv($this->maxSizeKb(), 1024);
    }

    /** Le navigateur sait-il lire ce type sans téléchargement ? */
    public function isPlayable(): bool
    {
        return in_array($this, [self::Audio, self::Video, self::Image], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $kind) {
            $options[$kind->value] = $kind->label();
        }

        return $options;
    }

    /** Repli sûr tant qu'aucun type n'est choisi dans le formulaire. */
    public static function fromValue(mixed $value): self
    {
        return self::tryFrom((string) $value) ?? self::Document;
    }

    private const MIME_BY_EXTENSION = [
        // Son
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4',
        'aac' => 'audio/aac', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
        'flac' => 'audio/flac', 'aif' => 'audio/aiff', 'aiff' => 'audio/aiff',
        'wma' => 'audio/x-ms-wma',

        // Vidéo
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime',
        'webm' => 'video/webm', 'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska',
        'wmv' => 'video/x-ms-wmv', 'mpeg' => 'video/mpeg', 'mpg' => 'video/mpeg',
        '3gp' => 'video/3gpp',

        // Image
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'avif' => 'image/avif',
        'bmp' => 'image/bmp', 'tif' => 'image/tiff', 'tiff' => 'image/tiff',
        'heic' => 'image/heic', 'heif' => 'image/heif',

        // Document
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'md' => 'text/markdown',
        'rtf' => 'application/rtf', 'csv' => 'text/csv',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'srt' => 'text/plain', 'vtt' => 'text/vtt',

        // Archive
        'zip' => 'application/zip', 'rar' => 'application/vnd.rar',
        '7z' => 'application/x-7z-compressed', 'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
    ];
}
