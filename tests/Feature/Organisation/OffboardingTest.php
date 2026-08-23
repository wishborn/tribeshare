<?php

use App\Enums\BookingStatus;
use App\Enums\HatType;
use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use App\Enums\OffboardingStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\LedgerEntry;
use App\Models\Llc;
use App\Models\Notification;
use App\Models\PayoutRequest;
use App\Models\Region;
use App\Models\User;
use App\Services\Organisation\ObligationService;
use App\Services\Organisation\OffboardingService;
use App\Services\Permissions\HatService;

beforeEach(function () {
    $this->offboarding = app(OffboardingService::class);
    $this->obligations = app(ObligationService::class);
    $this->hats = app(HatService::class);

    $this->region = Region::factory()->create();
    $this->llc = Llc::factory()->for($this->region)->create();
    $this->asset = Asset::factory()->for($this->llc)->create();

    $this->member = User::factory()->create();
    $this->hats->grant($this->member, HatType::LlcMember, $this->llc);
});

/**
 * A charge the member has not paid.
 */
function chargeAgainst(User $user, int $cents): LedgerEntry
{
    return LedgerEntry::create([
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->id,
        'direction' => LedgerDirection::Debit,
        'label' => LedgerLabel::AssetCharge,
        'amount_cents' => $cents,
        'description' => 'A stay',
    ]);
}

// --- Removal ------------------------------------------------------------

it('queues a removal rather than acting at once', function () {
    $queue = $this->offboarding->queueForRemoval($this->member, reason: 'Left the co-op.');

    expect($queue->status)->toBe(OffboardingStatus::Queued)
        ->and($this->member->refresh()->isRecycled())->toBeFalse()
        ->and($this->member->hats()->count())->toBe(1);
});

it('fires a removal once nothing is outstanding', function () {
    $this->offboarding->queueForRemoval($this->member);

    expect($this->offboarding->sweep()['removed'])->toBe(1)
        ->and($this->member->refresh()->isRecycled())->toBeTrue()
        ->and($this->member->hats()->count())->toBe(0);
});

it('holds a removal while a booking is still live', function () {
    Booking::factory()->for($this->asset)->for($this->member)->create([
        'status' => BookingStatus::Confirmed,
    ]);

    $this->offboarding->queueForRemoval($this->member);

    expect($this->offboarding->sweep()['removed'])->toBe(0)
        ->and($this->member->refresh()->isRecycled())->toBeFalse();
});

it('holds a removal while money is owed', function () {
    chargeAgainst($this->member, 75_00);

    $this->offboarding->queueForRemoval($this->member);

    // The prototype counted open bookings only, so a member could be
    // recycled owing for a stay they never paid for.
    expect($this->offboarding->sweep()['removed'])->toBe(0);

    expect(collect($this->obligations->forMember($this->member))->pluck('kind'))
        ->toContain('outstanding_charges');
});

it('holds a removal while credit would be lost', function () {
    LedgerEntry::create([
        'owner_type' => $this->member->getMorphClass(),
        'owner_id' => $this->member->id,
        'direction' => LedgerDirection::Credit,
        'label' => LedgerLabel::AssetIncome,
        'amount_cents' => 30_00,
        'description' => 'Income earned',
    ]);

    $this->offboarding->queueForRemoval($this->member);

    // Credit runs the other way: the member is owed, and firing the removal
    // would quietly confiscate it.
    expect($this->offboarding->sweep()['removed'])->toBe(0)
        ->and(collect($this->obligations->forMember($this->member))->pluck('kind'))
        ->toContain('unsettled_credit');
});

it('holds a removal while a payout is undecided', function () {
    PayoutRequest::factory()->for($this->member)->create();

    $this->offboarding->queueForRemoval($this->member);

    expect($this->offboarding->sweep()['removed'])->toBe(0)
        ->and(collect($this->obligations->forMember($this->member))->pluck('kind'))
        ->toContain('pending_payouts');
});

it('says what is outstanding rather than merely refusing', function () {
    chargeAgainst($this->member, 40_00);

    $obligations = $this->obligations->forMember($this->member);

    // "You cannot leave yet" is useless on its own.
    expect($obligations)->toHaveCount(1)
        ->and($obligations[0]->summary)->toBe('An unpaid balance.')
        ->and($obligations[0]->amountCents)->toBe(40_00);
});

it('cancels a queued removal', function () {
    $queue = $this->offboarding->queueForRemoval($this->member);
    $this->offboarding->cancelRemoval($queue);

    expect($queue->refresh()->status)->toBe(OffboardingStatus::Cancelled)
        ->and($this->offboarding->sweep()['removed'])->toBe(0);
});

it('queues a member once however often it is asked', function () {
    $first = $this->offboarding->queueForRemoval($this->member);
    $second = $this->offboarding->queueForRemoval($this->member);

    expect($second->id)->toBe($first->id);
});

// --- Leaving an LLC ------------------------------------------------------

it('lets a member queue their own departure', function () {
    $other = Llc::factory()->for($this->region)->create();
    $this->hats->grant($this->member, HatType::LlcMember, $other);

    $queue = $this->offboarding->queueLeave($this->member, $this->llc);

    // Self-service, but not instant.
    expect($queue->status)->toBe(OffboardingStatus::Queued)
        ->and($this->hats->holds($this->member, HatType::LlcMember, $this->llc))->toBeTrue();

    expect($this->offboarding->sweep()['left'])->toBe(1)
        ->and($this->hats->holds($this->member->fresh(), HatType::LlcMember, $this->llc))->toBeFalse();
});

it('will not let a departure strip a last membership', function () {
    $this->offboarding->queueLeave($this->member, $this->llc);

    $swept = $this->offboarding->sweep();

    // The guard holds here as it holds everywhere: a member must belong
    // somewhere. The departure stays queued rather than completing.
    expect($swept['left'])->toBe(0)
        ->and($this->hats->holds($this->member->fresh(), HatType::LlcMember, $this->llc))->toBeTrue();
});

it('carries on sweeping past a departure a guard refuses', function () {
    // One member cannot leave — this is their only LLC.
    $stuck = $this->member;
    $this->offboarding->queueLeave($stuck, $this->llc);

    // Another can, and must not be held up behind them.
    $other = Llc::factory()->for($this->region)->create();
    $free = User::factory()->create();
    $this->hats->grant($free, HatType::LlcMember, $this->llc);
    $this->hats->grant($free, HatType::LlcMember, $other);
    $this->offboarding->queueLeave($free, $this->llc);

    $swept = $this->offboarding->sweep();

    // The sweep is a scheduled job over every queue there is. One entry it
    // cannot complete must not take the rest down with it.
    expect($swept['left'])->toBe(1)
        ->and($this->hats->holds($free->fresh(), HatType::LlcMember, $this->llc))->toBeFalse()
        ->and($this->hats->holds($stuck->fresh(), HatType::LlcMember, $this->llc))->toBeTrue();
});

it('tells a member once that their departure is stuck', function () {
    $this->offboarding->queueLeave($this->member, $this->llc);

    $this->offboarding->sweep();
    $this->offboarding->sweep();

    // Repeating it every hour would be its own kind of failure.
    expect(Notification::query()
        ->where('user_id', $this->member->id)
        ->where('title', 'Your departure is on hold')
        ->count())->toBe(1);
});

it('holds a departure while a booking on that llc is live', function () {
    $other = Llc::factory()->for($this->region)->create();
    $this->hats->grant($this->member, HatType::LlcMember, $other);

    Booking::factory()->for($this->asset)->for($this->member)->create([
        'status' => BookingStatus::Confirmed,
    ]);

    $this->offboarding->queueLeave($this->member, $this->llc);

    expect($this->offboarding->sweep()['left'])->toBe(0);
});

it('ignores a booking on an llc the member is not leaving', function () {
    $other = Llc::factory()->for($this->region)->create();
    $otherAsset = Asset::factory()->for($other)->create();
    $this->hats->grant($this->member, HatType::LlcMember, $other);

    Booking::factory()->for($otherAsset)->for($this->member)->create([
        'status' => BookingStatus::Confirmed,
    ]);

    $this->offboarding->queueLeave($this->member, $this->llc);

    // Narrower than leaving the platform: only bookings on this LLC's assets.
    expect($this->offboarding->sweep()['left'])->toBe(1);
});

it('lets a member change their mind while it is queued', function () {
    $other = Llc::factory()->for($this->region)->create();
    $this->hats->grant($this->member, HatType::LlcMember, $other);

    $queue = $this->offboarding->queueLeave($this->member, $this->llc);
    $this->offboarding->cancelLeave($queue);

    expect($queue->refresh()->status)->toBe(OffboardingStatus::Cancelled)
        ->and($this->offboarding->sweep()['left'])->toBe(0);
});

it('refuses a departure from an llc the member is not in', function () {
    $stranger = User::factory()->create();

    expect(fn () => $this->offboarding->queueLeave($stranger, $this->llc))
        ->toThrow(RuntimeException::class, 'not a member');
});

it('refuses to cancel a departure that has already fired', function () {
    $other = Llc::factory()->for($this->region)->create();
    $this->hats->grant($this->member, HatType::LlcMember, $other);

    $queue = $this->offboarding->queueLeave($this->member, $this->llc);
    $this->offboarding->sweep();

    expect(fn () => $this->offboarding->cancelLeave($queue->refresh()))
        ->toThrow(RuntimeException::class, 'no longer queued');
});
