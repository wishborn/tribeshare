<?php

namespace Database\Factories;

use App\Models\BookingRide;
use App\Models\BookingRidePassenger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRidePassenger>
 */
class BookingRidePassengerFactory extends Factory
{
    protected $model = BookingRidePassenger::class;

    public function definition(): array
    {
        return [
            'booking_ride_id' => BookingRide::factory(),
            'user_id' => User::factory(),
            'joined_at' => now(),
        ];
    }
}
