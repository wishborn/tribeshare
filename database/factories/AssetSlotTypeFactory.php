<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetSlotType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetSlotType>
 */
class AssetSlotTypeFactory extends Factory
{
    protected $model = AssetSlotType::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'key' => '1hr',
            'label' => '1 hour',
            'duration_mesos' => 10,
            'price_cents' => 18_00,
            'enabled' => true,
        ];
    }

    public function of(string $key, int $durationMesos, int $priceCents): static
    {
        return $this->state(fn () => [
            'key' => $key,
            'duration_mesos' => $durationMesos,
            'price_cents' => $priceCents,
        ]);
    }

    public function requiringApproval(): static
    {
        return $this->state(fn () => ['approval_required' => true]);
    }
}
