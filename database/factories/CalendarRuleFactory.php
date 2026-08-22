<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\CalendarRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarRule>
 */
class CalendarRuleFactory extends Factory
{
    protected $model = CalendarRule::class;

    public function definition(): array
    {
        return [
            'calendar_id' => Calendar::factory(),
            'day' => 1,
            'meso_start' => 0,
            'meso_end' => 240,
            'bookable' => true,
            // 100 means normal price.
            'price_multiplier_pct' => 100,
            'draft' => false,
        ];
    }

    /**
     * A rule covering a whole day.
     */
    public function wholeDay(int $day): static
    {
        return $this->state(fn () => ['day' => $day, 'meso_start' => 0, 'meso_end' => 240]);
    }

    public function covering(int $day, int $mesoStart, int $mesoEnd): static
    {
        return $this->state(fn () => [
            'day' => $day,
            'meso_start' => $mesoStart,
            'meso_end' => $mesoEnd,
        ]);
    }

    public function pricedAt(float $multiplierPct): static
    {
        return $this->state(fn () => ['price_multiplier_pct' => $multiplierPct]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['bookable' => false]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['draft' => true]);
    }
}
