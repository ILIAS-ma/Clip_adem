<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Clipper]),
            'platform' => Platform::TikTok,
            'external_account_id' => fake()->unique()->numerify('##########'),
            'handle' => fake()->unique()->userName(),
            'followers_count' => fake()->numberBetween(500, 200_000),
            'verified_at' => now(),
            'is_active' => true,
        ];
    }
}
