<?php

namespace Database\Factories;

use App\Models\Llc;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Llc>
 */
class LlcFactory extends Factory
{
    protected $model = Llc::class;

    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => fake()->unique()->company(),
            'icon' => '🏢',
            'booking_fee_pct' => 0,
            'booking_fee_min_cents' => 0,
        ];
    }

    public function withFee(float $pct, int $minCents = 0): static
    {
        return $this->state(fn () => [
            'booking_fee_pct' => $pct,
            'booking_fee_min_cents' => $minCents,
        ]);
    }

    public function queuedForRetirement(): static
    {
        return $this->state(fn () => ['queued_for_retirement_at' => now()]);
    }
}
