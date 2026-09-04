<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Moderator = 'moderator';
    case Clipper = 'clipper';
    case Creator = 'creator';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrateur',
            self::Moderator => 'Modérateur',
            self::Clipper => 'Clippeur',
            self::Creator => 'Créateur',
        };
    }

    /**
     * Accès au panel Filament.
     *
     * Liste explicite, jamais « tout sauf clippeur » : ajouter un rôle ne doit
     * pas lui ouvrir le back-office et les paiements par simple oubli.
     */
    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Moderator], true);
    }

    /** Espace d'atterrissage après connexion. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::SuperAdmin, self::Moderator => '/admin',
            self::Creator => '/createur',
            self::Clipper => '/dashboard',
        };
    }
}
