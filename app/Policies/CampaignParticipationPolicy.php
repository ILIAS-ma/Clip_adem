<?php

namespace App\Policies;

use App\Models\CampaignParticipation;
use App\Models\User;

/**
 * Le staff tranche les demandes ; personne ne les crée ni ne les supprime
 * depuis le back-office — c'est le clippeur qui demande.
 */
class CampaignParticipationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, CampaignParticipation $participation): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CampaignParticipation $participation): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, CampaignParticipation $participation): bool
    {
        return false;
    }
}
