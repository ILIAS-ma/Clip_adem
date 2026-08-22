<?php

namespace App\Policies;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->isStaff();
    }

    /**
     * Une campagne qui a déjà consommé du budget ne se supprime pas : son
     * grand livre est une pièce comptable. On l'archive.
     */
    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin()
            && $campaign->spent_cents === 0
            && $campaign->status !== CampaignStatus::Active;
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return false;
    }
}
