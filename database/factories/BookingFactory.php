<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Llc;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startsAt = now()->addDay()->startOfHour();

        return [
            'asset_id' => Asset::factory(),
            'user_id' => User::factory(),
            // Snapshot the asset's LLC so the booking records the owner at
            // the time it was made, matching what the service does.
            'llc_id' => function (array $attributes): mixed {
                $asset = Asset::query()->find($attributes['asset_id']);

                return $asset instanceof Asset ? $asset->llc_id : Llc::factory();
            },
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'duration_mesos' => 20,
            'status' => BookingStatus::Confirmed,
            'priority' => 1,
        ];
    }

    public function from(Carbon $startsAt, Carbon $endsAt): static
    {
        return $this->state(fn () => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_mesos' => (int) round($startsAt->diffInMinutes($endsAt) / 6),
        ]);
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function priority(int $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }
}
