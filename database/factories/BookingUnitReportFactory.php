<?php

namespace Database\Factories;

use App\Enums\UnitReportStatus;
use App\Models\Booking;
use App\Models\BookingUnitReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingUnitReport>
 */
class BookingUnitReportFactory extends Factory
{
    protected $model = BookingUnitReport::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'status' => UnitReportStatus::AwaitingSubmission,
            'suggested_charge_cents' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $quantities
     */
    public function submitted(array $quantities, int $suggestedCents): static
    {
        return $this->state(fn () => [
            'status' => UnitReportStatus::PendingReview,
            'submitted_at' => now(),
            'quantities' => $quantities,
            'suggested_charge_cents' => $suggestedCents,
        ]);
    }
}
