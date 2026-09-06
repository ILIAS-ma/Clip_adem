<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignFunding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignFunding>
 */
class CampaignFundingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'amount_cents' => 100_000,
            'currency' => 'EUR',
            'method' => 'transfer',
            'reference' => 'VIR-'.fake()->unique()->numberBetween(10_000, 99_999),
            'received_at' => now(),
        ];
    }

    /** Remboursement au créateur : une ligne négative, jamais une suppression. */
    public function refund(int $cents): static
    {
        return $this->state(fn () => ['amount_cents' => -abs($cents)]);
    }
}
