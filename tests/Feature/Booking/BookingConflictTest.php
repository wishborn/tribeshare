<?php

use App\Enums\BookingStatus;
use App\Enums\HatType;
use App\Exceptions\BookingNotPermitted;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Hat;
use App\Models\User;
use App\Services\Booking\BookingService;

beforeEach(function () {
    $this->service = app(BookingService::class);
    $this->asset = Asset::factory()->create();
    $this->user = User::factory()->create();
    $this->asset->poolMembers()->attach($this->user);
});

function bookAt(Asset $asset, User $user, string $start, string $end, bool $allowBump = false): Booking
{
    return app(BookingService::class)->book(
        user: $user,
        asset: $asset,
        startsAt: Carbon\Carbon::parse($start),
        endsAt: Carbon\Carbon::parse($end),
        basePriceCents: 50_00,
        allowBump: $allowBump,
    );
}

it('creates a booking for a pool member', function () {
    $booking = bookAt($this->asset, $this->user, '2026-09-01 10:00', '2026-09-01 12:00');

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->duration_mesos)->toBe(20)
        ->and($booking->llc_id)->toBe($this->asset->llc_id);
});

it('refuses a member with no access to the asset', function () {
    $stranger = User::factory()->create();

    expect(fn () => bookAt($this->asset, $stranger, '2026-09-01 10:00', '2026-09-01 12:00'))
        ->toThrow(BookingNotPermitted::class, 'no access');
});

it('refuses an overlapping booking', function () {
    bookAt($this->asset, $this->user, '2026-09-01 10:00', '2026-09-01 12:00');

    $other = User::factory()->create();
    $this->asset->poolMembers()->attach($other);

    expect(fn () => bookAt($this->asset, $other, '2026-09-01 11:00', '2026-09-01 13:00'))
        ->toThrow(BookingNotPermitted::class, 'already booked');
});

it('allows a booking that starts exactly when another ends', function () {
    bookAt($this->asset, $this->user, '2026-09-01 10:00', '2026-09-01 12:00');

    $other = User::factory()->create();
    $this->asset->poolMembers()->attach($other);

    $second = bookAt($this->asset, $other, '2026-09-01 12:00', '2026-09-01 14:00');

    expect($second->exists)->toBeTrue()
        ->and(Booking::query()->live()->count())->toBe(2);
});

it('ignores cancelled bookings when detecting conflicts', function () {
    $first = bookAt($this->asset, $this->user, '2026-09-01 10:00', '2026-09-01 12:00');
    $first->update(['status' => BookingStatus::Cancelled]);

    $other = User::factory()->create();
    $this->asset->poolMembers()->attach($other);

    expect(bookAt($this->asset, $other, '2026-09-01 10:00', '2026-09-01 12:00')->exists)->toBeTrue();
});

it('does not conflict across different assets', function () {
    bookAt($this->asset, $this->user, '2026-09-01 10:00', '2026-09-01 12:00');

    $otherAsset = Asset::factory()->create();
    $otherAsset->poolMembers()->attach($this->user);

    expect(bookAt($otherAsset, $this->user, '2026-09-01 10:00', '2026-09-01 12:00')->exists)->toBeTrue();
});

it('books across midnight', function () {
    $booking = bookAt($this->asset, $this->user, '2026-09-01 22:00', '2026-09-02 08:00');

    expect($booking->spansMidnight())->toBeTrue()
        ->and($booking->duration_mesos)->toBe(100);
});

it('detects a conflict with an overnight booking from the previous day', function () {
    bookAt($this->asset, $this->user, '2026-09-01 22:00', '2026-09-02 08:00');

    $other = User::factory()->create();
    $this->asset->poolMembers()->attach($other);

    expect(fn () => bookAt($this->asset, $other, '2026-09-02 06:00', '2026-09-02 10:00'))
        ->toThrow(BookingNotPermitted::class, 'already booked');
});

it('rejects a range that ends before it starts', function () {
    expect(fn () => bookAt($this->asset, $this->user, '2026-09-01 12:00', '2026-09-01 10:00'))
        ->toThrow(BookingNotPermitted::class, 'must end after');
});

it('lets a higher priority member bump a lower one', function () {
    $displaced = bookAt($this->asset, $this->user, '2026-09-01 10:00', '2026-09-01 12:00');

    $owner = User::factory()->create();
    Hat::factory()->for($owner)->of(HatType::AssetOwner)->scopedTo($this->asset)->create();

    $booking = bookAt($this->asset, $owner, '2026-09-01 10:00', '2026-09-01 12:00', allowBump: true);

    expect($booking->bullied)->toBeTrue()
        ->and($displaced->refresh()->status)->toBe(BookingStatus::Bumped)
        ->and($displaced->bumped_by_user_id)->toBe($owner->id);
});

it('refuses to bump an equal or higher priority booking', function () {
    $owner = User::factory()->create();
    Hat::factory()->for($owner)->of(HatType::AssetOwner)->scopedTo($this->asset)->create();
    bookAt($this->asset, $owner, '2026-09-01 10:00', '2026-09-01 12:00');

    expect(fn () => bookAt($this->asset, $this->user, '2026-09-01 10:00', '2026-09-01 12:00', allowBump: true))
        ->toThrow(BookingNotPermitted::class, 'does not outrank');
});
