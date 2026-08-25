<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $target): bool
    {
        // Chacun édite son propre profil ; le reste passe par des actions
        // explicites (bannissement), jamais par un formulaire d'édition libre.
        return $user->is($target);
    }

    /**
     * Un compte ne se supprime pas : ses clips et son grand livre y font
     * référence. On bannit.
     */
    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function ban(User $user, User $target): bool
    {
        return $user->isStaff() && ! $user->is($target);
    }
}
