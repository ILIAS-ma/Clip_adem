<?php

namespace App\Policies;

use App\Models\Season;
use App\Models\User;

/** Le staff ouvre les saisons ; seul un super-admin en supprime une. */
class SeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Season $season): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Season $season): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Season $season): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
