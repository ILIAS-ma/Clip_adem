<?php

namespace App\Enums;

enum Platform: string
{
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case Instagram = 'instagram';

    public function label(): string
    {
        return match ($this) {
            self::TikTok => 'TikTok',
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
        };
    }
}
