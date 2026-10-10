<?php

namespace Database\Factories;

use App\Enums\CreatorKind;
use App\Models\Creator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Creator>
 */
class CreatorFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->name();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'kind' => CreatorKind::Artist,
            'bio' => fake()->paragraph(),
            'is_active' => true,
        ];
    }

    public function streamer(): static
    {
        return $this->state(fn () => [
            'kind' => CreatorKind::Streamer,
            'twitch_handle' => fake()->unique()->userName(),
            'vod_url' => 'https://twitch.tv/'.fake()->userName().'/videos',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
