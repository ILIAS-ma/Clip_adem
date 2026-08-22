<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Models\Artist;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'artist_id' => Artist::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'brief' => fake()->paragraph(),
            'status' => CampaignStatus::Active,
            'currency' => 'EUR',
            'budget_total_cents' => 100_000, // 1 000 €
            'spent_cents' => 0,
            'min_views_per_clip' => 0,
            'requires_approval' => true,
        ];
    }

    /** Budget exprimé en euros, pour que les tests restent lisibles. */
    public function budget(float $euros): static
    {
        return $this->state(fn () => ['budget_total_cents' => (int) round($euros * 100)]);
    }

    public function status(CampaignStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    /**
     * Ajoute un taux CPM après création. Par défaut 1 € pour 1000 vues sur
     * TikTok, soit 0,1 centime la vue.
     */
    public function withRate(Platform $platform = Platform::TikTok, int $ratePer1kCents = 100): static
    {
        return $this->afterCreating(function (Campaign $campaign) use ($platform, $ratePer1kCents) {
            $campaign->rates()->create([
                'platform' => $platform,
                'rate_per_1k_cents' => $ratePer1kCents,
                'is_enabled' => true,
            ]);
        });
    }
}
