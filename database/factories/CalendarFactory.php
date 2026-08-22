<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Calendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calendar>
 */
class CalendarFactory extends Factory
{
    protected $model = Calendar::class;

    public function definition(): array
    {
        return [
            'schedulable_type' => (new Asset)->getMorphClass(),
            'schedulable_id' => Asset::factory(),
            'month' => now()->format('Y-m'),
        ];
    }

    public function for_(Asset $asset, string $month): static
    {
        return $this->state(fn () => [
            'schedulable_type' => $asset->getMorphClass(),
            'schedulable_id' => $asset->getKey(),
            'month' => $month,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()]);
    }
}
