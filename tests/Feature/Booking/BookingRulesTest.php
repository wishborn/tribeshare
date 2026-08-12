<?php

use App\Enums\HatType;
use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use App\Exceptions\BookingNotPermitted;
use App\Models\Asset;
use App\Models\Hat;
use App\Models\LedgerEntry;
use App\Models\Llc;
use App\Models\Region;
use App\Models\User;
use App\Services\Booking\BookingService;

beforeEach(function () {
    $this->service = app(BookingService::class);
    $this->asset = Asset::factory()->create();
    $this->user = User::factory()->create();
    $this->asset->poolMembers()->attach($this->user);
});

function attemptBooking(Asset $asset, User $user, int $basePriceCents = 50_00): void
{
    app(BookingService::class)->book(
        user: $user,
        asset: $asset,
        startsAt: Carbon\Carbon::parse('2026-09-01 10:00'),
        endsAt: Carbon\Carbon::parse('2026-09-01 12:00'),
        basePriceCents: $basePriceCents,
    );
}

it('never lets an RCM hold a booking', function () {
    $rcm = User::factory()->create();
    Hat::factory()->for($rcm)->of(HatType::Rcm)->create();
    $this->asset->poolMembers()->attach($rcm);

    expect(fn () => attemptBooking($this->asset, $rcm))
        ->toThrow(BookingNotPermitted::class, 'never holds one');
});

it('freezes booking when the asset is queued for retirement', function () {
    $this->asset->update(['queued_for_retirement_at' => now()]);

    expect(fn () => attemptBooking($this->asset, $this->user))
        ->toThrow(BookingNotPermitted::class, 'queued for retirement');
});

it('freezes booking when the owning LLC is queued for retirement', function () {
    $this->asset->llc->update(['queued_for_retirement_at' => now()]);

    expect(fn () => attemptBooking($this->asset->refresh(), $this->user))
        ->toThrow(BookingNotPermitted::class, 'queued for retirement');
});

it('freezes booking when the region above the LLC is queued for retirement', function () {
    $this->asset->llc->region->update(['queued_for_retirement_at' => now()]);

    expect(fn () => attemptBooking($this->asset->refresh(), $this->user))
        ->toThrow(BookingNotPermitted::class, 'queued for retirement');
});

it('blocks booking while a charge is overdue', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(20_00)->agedDays(22)->create();

    expect(fn () => attemptBooking($this->asset, $this->user))
        ->toThrow(BookingNotPermitted::class, 'suspended');
});

it('blocks a booking that would breach the balance limit', function () {
    $this->user->update(['carried_balance_limit_cents' => 10_00]);

    expect(fn () => attemptBooking($this->asset, $this->user, basePriceCents: 50_00))
        ->toThrow(BookingNotPermitted::class, 'carried balance limit');
});

it('grants access through an LLC role without pool membership', function () {
    $llcMember = User::factory()->create();
    Hat::factory()->for($llcMember)->of(HatType::LlcMember)->scopedTo($this->asset->llc)->create();

    attemptBooking($this->asset, $llcMember);

    expect($llcMember->bookings()->count())->toBe(1);
});

it('posts six balancing entries covering the whole charge', function () {
    $owner = User::factory()->create();
    $region = Region::factory()->withFee(5)->create();
    $llc = Llc::factory()->for($region)->withFee(10)->create();
    $asset = Asset::factory()->for($llc)->create(['main_owner_id' => $owner->id]);
    $asset->poolMembers()->attach($this->user);

    attemptBooking($asset, $this->user, basePriceCents: 100_00);

    $booking = $this->user->bookings()->firstOrFail();

    // 100.00 base, 10% LLC fee, 5% regional fee.
    expect($booking->llc_fee_cents)->toBe(10_00)
        ->and($booking->region_fee_cents)->toBe(5_00)
        ->and($booking->total_cents)->toBe(115_00);

    $entries = $booking->ledgerEntries;
    $debits = $entries->where('direction', LedgerDirection::Debit)->sum('amount_cents');
    $credits = $entries->where('direction', LedgerDirection::Credit)->sum('amount_cents');

    expect($debits)->toBe(115_00)
        ->and($credits)->toBe(115_00);
});

it('redirects voluntary contributions out of the owner income, still balancing', function () {
    $owner = User::factory()->create();
    $region = Region::factory()->create();
    $llc = Llc::factory()->for($region)->create();
    $asset = Asset::factory()->for($llc)->contributing(llcPct: 20, regionPct: 10)
        ->create(['main_owner_id' => $owner->id]);
    $asset->poolMembers()->attach($this->user);

    attemptBooking($asset, $this->user, basePriceCents: 100_00);

    $booking = $this->user->bookings()->firstOrFail();
    $entries = $booking->ledgerEntries;

    $ownerIncome = $entries->firstWhere('label', LedgerLabel::AssetIncome);

    // The owner keeps 70; the LLC and region take 20 and 10.
    expect($ownerIncome->amount_cents)->toBe(70_00)
        ->and($entries->where('direction', LedgerDirection::Debit)->sum('amount_cents'))
        ->toBe($entries->where('direction', LedgerDirection::Credit)->sum('amount_cents'));
});
