<?php

namespace App\Policies;

use App\Models\Clip;
use App\Models\User;

/**
 * Les clips sont créés par le module clippeur : le back-office modère, il ne
 * crée ni ne supprime. Une suppression effacerait une pièce du grand livre.
 */
class ClipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Clip $clip): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return false;
    }

    /** Modérer, c'est « mettre à jour » au sens des policies. */
    public function update(User $user, Clip $clip): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Clip $clip): bool
    {
        return false;
    }
}
