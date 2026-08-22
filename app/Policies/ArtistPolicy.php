<?php

namespace App\Policies;

use App\Models\Artist;
use App\Models\User;

/**
 * Deux rôles seulement : un modérateur gère le catalogue, un super-admin est
 * le seul à pouvoir supprimer. Pas de package de permissions pour ça.
 */
class ArtistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Artist $artist): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Artist $artist): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Artist $artist): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Artist $artist): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Artist $artist): bool
    {
        return false;
    }
}
