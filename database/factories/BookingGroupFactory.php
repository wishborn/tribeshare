<?php

namespace Database\Factories;

use App\Enums\GroupPriceMode;
use App\Enums\SplitMode;
use App\Models\BookingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingGroup>
 */
class BookingGroupFactory extends Factory
{
    protected $model = BookingGroup::class;

    public function definition(): array
    {
        return [
            'split_mode' => SplitMode::Equal,
            'price_mode' => GroupPriceMode::None,
            'size' => 2,
            'total_cents' => 100_00,
        ];
    }
}
