<?php

namespace Database\Factories;

use App\Enums\NotificationKind;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => NotificationKind::System,
            'title' => $this->faker->sentence(4),
        ];
    }

    public function of(NotificationKind $kind): static
    {
        return $this->state(fn () => ['kind' => $kind]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
