<?php

namespace App\Policies;

use App\Models\Payout;
use App\Models\User;

/**
 * L'argent est réservé au super-admin.
 *
 * Un modérateur juge des clips ; il ne déclenche pas de virements.
 */
class PayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Payout $payout): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payout $payout): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Payout $payout): bool
    {
        return false;
    }
}
