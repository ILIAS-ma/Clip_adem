<?php

namespace Database\Factories;

use App\Enums\ParticipationStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignParticipation>
 */
class CampaignParticipationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'user_id' => User::factory()->state(['role' => UserRole::Clipper]),
            'social_account_id' => SocialAccount::factory(),
            'status' => ParticipationStatus::Approved,
            'applied_at' => now()->subDay(),
            'approved_at' => now(),
        ];
    }
}
