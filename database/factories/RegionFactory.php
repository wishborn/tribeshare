<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'icon' => '🌐',
            'visible' => true,
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
