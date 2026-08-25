<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'user_id' => User::factory()->state([
                'role' => UserRole::Clipper,
                'paypal_email' => $email,
            ]),
            'amount_cents' => fake()->numberBetween(1_000, 20_000),
            'currency' => 'EUR',
            'status' => PayoutStatus::Requested,
            'paypal_email' => $email,
            'requested_at' => now(),
        ];
    }

    public function status(PayoutStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
