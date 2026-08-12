<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Llc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'llc_id' => Llc::factory(),
            'name' => fake()->words(2, true),
            'type' => 'cabin',
            'settings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => [...($attributes['settings'] ?? []), ...$settings],
        ]);
    }

    public function contributing(float $llcPct, float $regionPct): static
    {
        return $this->withSettings([
            'voluntary_contrib_llc_pct' => $llcPct,
            'voluntary_contrib_region_pct' => $regionPct,
        ]);
    }

    public function queuedForRetirement(): static
    {
        return $this->state(fn () => ['queued_for_retirement_at' => now()]);
    }
}
