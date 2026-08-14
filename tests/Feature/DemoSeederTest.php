<?php

use App\Enums\BookingStatus;
use App\Enums\ChargeStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use App\Enums\PaymentStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\BookingOffer;
use App\Models\BookingUnitReport;
use App\Models\LedgerEntry;
use App\Models\Llc;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Region;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use Database\Seeders\TribeShareDemoSeeder;

/**
 * The demo dataset is run on every rebuild for local browser testing, so it
 * has to keep showing every state worth seeing. These assertions are what
 * "worth seeing" means — if the schema grows and the seeder stops producing
 * one of them, this fails rather than the demo quietly going flat.
 */
beforeEach(function () {
    $this->seed(TribeShareDemoSeeder::class);
    $this->ledger = app(LedgerService::class);
});

function member(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

it('seeds the org structure', function () {
    expect(Region::count())->toBe(1)
        ->and(Llc::count())->toBe(2)
        ->and(Asset::count())->toBe(3)
        ->and(User::count())->toBe(6);
});

it('shows a member suspended by a genuinely overdue charge', function () {
    $suspended = member('faye@tribeshare.test');

    expect($this->ledger->hasOverdueCharges($suspended))->toBeTrue()
        ->and($suspended->billing_suspended)->toBeTrue();
});

it('shows a charge that is due but not yet overdue', function () {
    $cleo = member('cleo@tribeshare.test');

    $statuses = collect($this->ledger->chargeAllocations($cleo))
        ->map(fn ($allocation) => $allocation->status);

    expect($statuses)->toContain(ChargeStatus::Due)
        ->and($this->ledger->hasOverdueCharges($cleo))->toBeFalse();
});

it('shows credit both matured and still maturing', function () {
    $availability = $this->ledger->creditAvailability(member('ada@tribeshare.test'));

    expect($availability->availableCents)->toBeGreaterThan(0)
        ->and($availability->hasMaturingCredit())->toBeTrue();
});

it('covers every booking status worth looking at', function () {
    $statuses = Booking::query()->pluck('status');

    expect($statuses)->toContain(BookingStatus::Completed)
        ->and($statuses)->toContain(BookingStatus::Active)
        ->and($statuses)->toContain(BookingStatus::Confirmed)
        ->and($statuses)->toContain(BookingStatus::Pending);
});

it('includes an overnight booking', function () {
    $overnight = Booking::all()->first(fn (Booking $b) => $b->spansMidnight());

    expect($overnight)->not->toBeNull();
});

it('includes an offer still open and a usage report still awaited', function () {
    expect(BookingOffer::query()->whereNull('picked_up_at')->count())->toBe(1)
        ->and(BookingUnitReport::count())->toBe(1);
});

it('balances every booking it posts', function () {
    Booking::with('ledgerEntries')->get()->each(function (Booking $booking) {
        $entries = $booking->ledgerEntries;

        if ($entries->isEmpty()) {
            return;
        }

        $debits = $entries->where('direction', LedgerDirection::Debit)->sum('amount_cents');
        $credits = $entries->where('direction', LedgerDirection::Credit)->sum('amount_cents');

        expect($debits)->toBe($credits, "Booking {$booking->id} does not balance");
    });
});

it('records a correction as a balancing entry rather than an edit', function () {
    $reversal = LedgerEntry::where('label', LedgerLabel::Reversal)->firstOrFail();

    expect($reversal->reverses_id)->not->toBeNull()
        ->and($reversal->reason)->toBe('Charged to the wrong member')
        // The mistake is still on record alongside its correction.
        ->and(LedgerEntry::find($reversal->reverses_id))->not->toBeNull();
});

it('leaves a payment waiting on an RCM', function () {
    expect(Payment::query()->where('status', PaymentStatus::Pending)->count())->toBe(1)
        ->and(PayoutRequest::query()->pending()->count())->toBe(1);
});
