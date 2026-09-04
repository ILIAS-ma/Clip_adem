<?php

namespace App\Policies;

use App\Models\Creator;
use App\Models\User;

/**
 * Deux rôles seulement : un modérateur gère le catalogue, un super-admin est
 * le seul à pouvoir supprimer. Pas de package de permissions pour ça.
 */
class CreatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Creator $creator): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Creator $creator): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Creator $creator): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Creator $creator): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Creator $creator): bool
    {
        return false;
    }
}
