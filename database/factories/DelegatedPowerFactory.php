<?php

namespace Database\Factories;

use App\Enums\AssetPower;
use App\Enums\PowerTier;
use App\Models\Asset;
use App\Models\DelegatedPower;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegatedPower>
 */
class DelegatedPowerFactory extends Factory
{
    protected $model = DelegatedPower::class;

    public function definition(): array
    {
        return [
            'powerable_type' => (new Asset)->getMorphClass(),
            'powerable_id' => Asset::factory(),
            'tier' => PowerTier::Manager,
            'power' => AssetPower::ApproveBookings->value,
            'granted' => true,
        ];
    }
}
