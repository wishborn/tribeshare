<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingEvent>
 */
class BookingEventFactory extends Factory
{
    protected $model = BookingEvent::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'host_user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'host_fee_cents' => 0,
        ];
    }
}
