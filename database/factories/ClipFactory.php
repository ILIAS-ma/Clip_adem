<?php

namespace Database\Factories;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clip>
 */
class ClipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'user_id' => User::factory()->state(['role' => UserRole::Clipper]),
            'platform' => Platform::TikTok,
            'external_post_id' => fake()->unique()->numerify('##############'),
            'url' => 'https://www.tiktok.com/@clippeur/video/'.fake()->unique()->numerify('##############'),
            'posted_at' => now()->subDay(),
            'status' => ClipStatus::Approved,
            'views_total' => 0,
            'paid_views' => 0,
            'earned_cents' => 0,
        ];
    }

    public function status(ClipStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function onPlatform(Platform $platform): static
    {
        return $this->state(fn () => ['platform' => $platform]);
    }
}
