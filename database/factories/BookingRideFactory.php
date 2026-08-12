<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingRide;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRide>
 */
class BookingRideFactory extends Factory
{
    protected $model = BookingRide::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'driver_user_id' => User::factory(),
            'miles_logged' => 0,
        ];
    }
}
