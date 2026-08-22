<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\CalendarTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarTemplate>
 */
class CalendarTemplateFactory extends Factory
{
    protected $model = CalendarTemplate::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'name' => 'Summer weekdays',
            'rules' => [
                ['day' => 1, 'meso_start' => 0, 'meso_end' => 240, 'price_multiplier_pct' => 100],
            ],
            'source_days_in_month' => 31,
        ];
    }
}
