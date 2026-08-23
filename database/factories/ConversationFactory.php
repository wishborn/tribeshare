<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'created_by' => User::factory(),
            'is_direct' => false,
        ];
    }

    public function direct(): static
    {
        return $this->state(fn () => ['is_direct' => true, 'name' => null]);
    }
}
