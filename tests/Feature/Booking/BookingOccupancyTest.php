<?php

use App\Enums\BookingStatus;
use App\Enums\HatType;
use App\Exceptions\BookingNotPermitted;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\CollectionItem;
use App\Models\Hat;
use App\Models\User;
use App\Services\Booking\BookingService;
use Carbon\Carbon;

/**
 * The two things that make booking conflict more than "one per slot":
 * turnaround buffers around a booking, and collection items standing for
 * several identical units.
 */
beforeEach(function () {
    $this->service = app(BookingService::class);
});

function poolMember(Asset $asset): User
{
    $user = User::factory()->create();
    $asset->poolMembers()->attach($user);

    return $user;
}

function book(Asset $asset, User $user, string $start, string $end, ?CollectionItem $item = null, bool $allowBump = false): Booking
{
    return app(BookingService::class)->book(
        user: $user,
        asset: $asset,
        startsAt: Carbon::parse($start),
        endsAt: Carbon::parse($end),
        basePriceCents: 10_00,
        allowBump: $allowBump,
        item: $item,
    );
}

// --- Bookends ---------------------------------------------------------

it('records the occupied range as the booked range when there are no buffers', function () {
    $asset = Asset::factory()->create();
    $booking = book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00');

    expect($booking->occupies_from->toDateTimeString())->toBe('2026-09-01 10:00:00')
        ->and($booking->occupies_until->toDateTimeString())->toBe('2026-09-01 12:00:00');
});

it('widens the occupied range by the asset buffers', function () {
    // 10 mesos before (1hr), 40 after (4hr) — a house's turnaround.
    $asset = Asset::factory()
        ->withBookends(10, 40)
        ->create();

    $booking = book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00');

    expect($booking->occupies_from->toDateTimeString())->toBe('2026-09-01 09:00:00')
        ->and($booking->occupies_until->toDateTimeString())->toBe('2026-09-01 16:00:00')
        ->and($booking->bookend_before_mesos)->toBe(10)
        ->and($booking->bookend_after_mesos)->toBe(40);
});

it('refuses a back-to-back booking that would leave no turnaround', function () {
    $asset = Asset::factory()->withBookends(0, 40)->create();
    book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00');

    // Starts when the previous booking ends, but inside its four-hour tail.
    expect(fn () => book($asset, poolMember($asset), '2026-09-01 12:00', '2026-09-01 14:00'))
        ->toThrow(BookingNotPermitted::class, 'already booked');
});

it('allows a booking that begins once the turnaround has elapsed', function () {
    $asset = Asset::factory()->withBookends(0, 40)->create();
    book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00');

    $second = book($asset, poolMember($asset), '2026-09-01 16:00', '2026-09-01 18:00');

    expect($second->exists)->toBeTrue();
});

it('applies the leading buffer as well, blocking a booking that ends too close', function () {
    $asset = Asset::factory()->withBookends(10, 0)->create();
    book($asset, poolMember($asset), '2026-09-01 12:00', '2026-09-01 14:00');

    // Ends at 11:30, inside the hour of preparation before 12:00.
    expect(fn () => book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 11:30'))
        ->toThrow(BookingNotPermitted::class, 'already booked');
});

it('keeps the buffers it was made under when settings later change', function () {
    $asset = Asset::factory()->withBookends(0, 40)->create();
    $booking = book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00');

    $asset->update(['bookend_after_mesos' => 0]);

    expect($booking->refresh()->occupies_until->toDateTimeString())->toBe('2026-09-01 16:00:00');
});

// --- Collection item capacity -----------------------------------------

it('admits as many concurrent bookings as the item has units', function () {
    $asset = Asset::factory()->create();
    $item = CollectionItem::factory()->for($asset)->quantity(3)->create();

    book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $item);
    book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $item);
    $third = book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $item);

    expect($third->exists)->toBeTrue()
        ->and(Booking::query()->live()->count())->toBe(3);
});

it('refuses the booking that would exceed the units available', function () {
    $asset = Asset::factory()->create();
    $item = CollectionItem::factory()->for($asset)->quantity(2)->create();

    book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $item);
    book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $item);

    expect(fn () => book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $item))
        ->toThrow(BookingNotPermitted::class, 'already booked');
});

it('does not let one collection item contend with another', function () {
    $asset = Asset::factory()->create();
    $spanner = CollectionItem::factory()->for($asset)->create(['name' => 'Spanner']);
    $drill = CollectionItem::factory()->for($asset)->create(['name' => 'Drill']);

    book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $spanner);
    $second = book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $drill);

    expect($second->exists)->toBeTrue();
});

it('does not let an item booking contend with the asset itself', function () {
    $asset = Asset::factory()->create();
    $item = CollectionItem::factory()->for($asset)->create();

    book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00');
    $second = book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $item);

    expect($second->exists)->toBeTrue();
});

it('displaces only as many bookings as it needs to fit', function () {
    $asset = Asset::factory()->create();
    $item = CollectionItem::factory()->for($asset)->quantity(2)->create();

    $first = book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $item);
    $second = book($asset, poolMember($asset), '2026-09-01 10:00', '2026-09-01 12:00', $item);

    $owner = poolMember($asset);
    Hat::factory()->for($owner)->of(HatType::AssetOwner)->scopedTo($asset)->create();

    book($asset, $owner, '2026-09-01 10:00', '2026-09-01 12:00', $item, allowBump: true);

    // Two units, three claimants — exactly one had to go.
    $bumped = collect([$first, $second])
        ->filter(fn (Booking $b) => $b->refresh()->status === BookingStatus::Bumped);

    expect($bumped)->toHaveCount(1);
});
