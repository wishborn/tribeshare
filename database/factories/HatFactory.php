<?php

namespace Database\Factories;

use App\Enums\HatType;
use App\Models\Hat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Hat>
 */
class HatFactory extends Factory
{
    protected $model = Hat::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => HatType::LlcMember,
            'active' => true,
        ];
    }

    public function of(HatType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    /**
     * Scope the hat to a specific entity. Omit for the "all" scope.
     */
    public function scopedTo(Model $entity): static
    {
        return $this->state(fn () => [
            'scopeable_type' => $entity->getMorphClass(),
            'scopeable_id' => $entity->getKey(),
        ]);
    }
}
