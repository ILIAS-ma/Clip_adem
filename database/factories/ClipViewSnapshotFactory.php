<?php

namespace Database\Factories;

use App\Models\Clip;
use App\Models\ClipViewSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClipViewSnapshot>
 */
class ClipViewSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clip_id' => Clip::factory(),
            'views' => fake()->numberBetween(100, 100_000),
            'source' => 'api',
            'captured_at' => now(),
        ];
    }
}
