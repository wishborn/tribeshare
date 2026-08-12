<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingQuestionnaireResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingQuestionnaireResponse>
 */
class BookingQuestionnaireResponseFactory extends Factory
{
    protected $model = BookingQuestionnaireResponse::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'question' => fake()->sentence().'?',
            'answer' => fake()->sentence(),
        ];
    }
}
