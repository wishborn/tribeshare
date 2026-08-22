<?php

namespace Database\Factories;

use App\Enums\GroupPriceMode;
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
            'status' => 'approved',
            // Presentation and assurance only — everything on the booking
            // path is a column now.
            'settings' => [],
        ];
    }

    /**
     * Turnaround reserved around every booking, in mesos.
     */
    public function withBookends(int $before, int $after): static
    {
        return $this->state(fn () => [
            'bookend_before_mesos' => $before,
            'bookend_after_mesos' => $after,
        ]);
    }

    /**
     * Redirect a share of the owner's income to the LLC and region.
     */
    public function contributing(float $llcPct, float $regionPct): static
    {
        return $this->state(fn () => [
            'voluntary_contrib_llc_pct' => $llcPct,
            'voluntary_contrib_region_pct' => $regionPct,
        ]);
    }

    public function groupPricing(GroupPriceMode $mode, float $multiplier = 1, int $premiumCents = 0): static
    {
        return $this->state(fn () => [
            'group_price_mode' => $mode,
            'group_multiplier' => $multiplier,
            'group_premium_cents' => $premiumCents,
        ]);
    }

    /**
     * Presentation fields that genuinely belong in the blob.
     *
     * @param  array<string, mixed>  $settings
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => [...($attributes['settings'] ?? []), ...$settings],
        ]);
    }

    public function queuedForRetirement(): static
    {
        return $this->state(fn () => ['queued_for_retirement_at' => now()]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}
