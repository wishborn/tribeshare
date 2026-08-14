<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\CollectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionItem>
 */
class CollectionItemFactory extends Factory
{
    protected $model = CollectionItem::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            // The column caps names at 25 characters.
            'name' => substr(fake()->word().' '.fake()->word(), 0, 25),
            'quantity' => 1,
            'position' => 0,
        ];
    }

    public function quantity(int $quantity): static
    {
        return $this->state(fn () => ['quantity' => $quantity]);
    }
}
