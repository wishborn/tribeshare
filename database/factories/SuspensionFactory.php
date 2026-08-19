<?php

namespace Database\Factories;

use App\Models\Llc;
use App\Models\Suspension;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suspension>
 */
class SuspensionFactory extends Factory
{
    protected $model = Suspension::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'suspended_at' => now(),
        ];
    }

    /**
     * Scoped to one LLC rather than global.
     */
    public function from(Llc $llc): static
    {
        return $this->state(fn () => [
            'scopeable_type' => $llc->getMorphClass(),
            'scopeable_id' => $llc->id,
        ]);
    }

    public function lifted(): static
    {
        return $this->state(fn () => ['lifted_at' => now()]);
    }
}
