<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Même partage que pour le catalogue : un modérateur range les campagnes, un
 * super-admin est le seul à pouvoir supprimer une catégorie — ce qui retire
 * un onglet du classement à tous les clippeurs.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Category $category): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isStaff();
    }

    public function reorder(User $user): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
