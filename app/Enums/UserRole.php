<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Moderator = 'moderator';
    case Clipper = 'clipper';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrateur',
            self::Moderator => 'Modérateur',
            self::Clipper => 'Clippeur',
        };
    }

    /** Accès au panel Filament. */
    public function isStaff(): bool
    {
        return $this !== self::Clipper;
    }
}
