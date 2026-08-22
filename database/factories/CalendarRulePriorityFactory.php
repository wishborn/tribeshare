<?php

namespace Database\Factories;

use App\Models\CalendarRule;
use App\Models\CalendarRulePriority;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarRulePriority>
 */
class CalendarRulePriorityFactory extends Factory
{
    protected $model = CalendarRulePriority::class;

    public function definition(): array
    {
        return [
            'calendar_rule_id' => CalendarRule::factory(),
            'user_id' => User::factory(),
            'position' => 0,
        ];
    }

    public function barred(): static
    {
        return $this->state(fn () => ['cannot_book' => true]);
    }

    public function unbumpable(): static
    {
        return $this->state(fn () => ['unbumpable' => true]);
    }
}
