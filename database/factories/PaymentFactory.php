<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount_cents' => 50_00,
            'status' => PaymentStatus::Pending,
            'submitted_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function of(int $cents): static
    {
        return $this->state(fn () => ['amount_cents' => $cents]);
    }
}
