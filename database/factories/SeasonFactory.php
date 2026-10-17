<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Saison '.fake()->unique()->numberBetween(1, 999),
            'starts_on' => today()->subDays(10),
            'ends_on' => today()->addDays(20),
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'starts_on' => today()->subDays(60),
            'ends_on' => today()->subDays(31),
        ]);
    }
}
