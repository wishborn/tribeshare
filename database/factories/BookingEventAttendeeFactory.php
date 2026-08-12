<?php

namespace Database\Factories;

use App\Models\BookingEvent;
use App\Models\BookingEventAttendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingEventAttendee>
 */
class BookingEventAttendeeFactory extends Factory
{
    protected $model = BookingEventAttendee::class;

    public function definition(): array
    {
        return [
            'booking_event_id' => BookingEvent::factory(),
            'user_id' => User::factory(),
            'rsvp_status' => 'going',
        ];
    }
}
