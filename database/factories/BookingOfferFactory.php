<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingOffer>
 */
class BookingOfferFactory extends Factory
{
    protected $model = BookingOffer::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'offered_at' => now(),
            'giver_pct' => 0,
            'picker_pct' => 100,
        ];
    }
}
