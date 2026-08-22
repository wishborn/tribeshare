<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetUnitPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetUnitPrice>
 */
class AssetUnitPriceFactory extends Factory
{
    protected $model = AssetUnitPrice::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'unit' => 'Mile',
            'label' => 'Mileage',
            'rate_cents' => 35,
        ];
    }

    public function of(string $unit, int $rateCents): static
    {
        return $this->state(fn () => ['unit' => $unit, 'rate_cents' => $rateCents]);
    }
}
