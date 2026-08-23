<?php

use App\Enums\BookingStatus;
use App\Enums\HatType;
use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use App\Enums\NotificationKind;
use App\Enums\QueueSource;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\LedgerEntry;
use App\Models\Llc;
use App\Models\Notification;
use App\Models\Region;
use App\Models\User;
use App\Services\Organisation\LifecycleService;
use App\Services\Organisation\ObligationService;
use App\Services\Organisation\OffboardingService;
use App\Services\Permissions\HatService;

beforeEach(function () {
    $this->lifecycle = app(LifecycleService::class);
    $this->offboarding = app(OffboardingService::class);
    $this->obligations = app(ObligationService::class);
    $this->hats = app(HatService::class);

    $this->region = Region::factory()->create();
    $this->llc = Llc::factory()->for($this->region)->create();
    $this->asset = Asset::factory()->for($this->llc)->create();
});

// --- The cascade ---------------------------------------------------------

it('cascades a region retirement down to its assets', function () {
    $this->lifecycle->retireRegion($this->region);

    expect($this->region->refresh()->isQueuedForRetirement())->toBeTrue()
        ->and($this->llc->refresh()->isQueuedForRetirement())->toBeTrue()
        ->and($this->asset->refresh()->isQueuedForRetirement())->toBeTrue();
});

it('records what queued each entity', function () {
    $this->lifecycle->retireRegion($this->region);

    // Restoring has to unwind exactly what this retirement caused, so the
    // cascade records its own provenance.
    expect($this->region->refresh()->queued_source)->toBe(QueueSource::Direct)
        ->and($this->llc->refresh()->queued_source)->toBe(QueueSource::Region)
        ->and($this->asset->refresh()->queued_source)->toBe(QueueSource::Llc);
});

it('freezes booking anywhere along the chain', function () {
    $this->lifecycle->retireRegion($this->region);

    expect($this->asset->refresh()->isFrozenForRetirement())->toBeTrue();
});

it('unwinds the whole cascade on restore', function () {
    $this->lifecycle->retireRegion($this->region);
    $this->lifecycle->restoreRegion($this->region->refresh());

    expect($this->region->refresh()->isQueuedForRetirement())->toBeFalse()
        ->and($this->llc->refresh()->isQueuedForRetirement())->toBeFalse()
        ->and($this->asset->refresh()->isQueuedForRetirement())->toBeFalse();
});

it('leaves an llc retired on its own account alone', function () {
    $ownAccount = Llc::factory()->for($this->region)->create();
    $this->lifecycle->retireLlc($ownAccount);

    $this->lifecycle->retireRegion($this->region->refresh());
    $this->lifecycle->restoreRegion($this->region->refresh());

    // It was already queued when the region went, so the region never
    // queued it and the restore has no business un-queuing it.
    expect($ownAccount->refresh()->isQueuedForRetirement())->toBeTrue()
        ->and($this->llc->refresh()->isQueuedForRetirement())->toBeFalse();
});

it('leaves an llc separately condemned alone', function () {
    $this->lifecycle->retireRegion($this->region);

    $condemned = $this->llc->refresh();
    $this->lifecycle->markForDeletion($condemned);

    $this->lifecycle->restoreRegion($this->region->refresh());

    // A restore must not resurrect something condemned on its own account.
    expect($condemned->refresh()->isQueuedForRetirement())->toBeTrue();
});

it('tells the llc owners when the region above them retires', function () {
    $owner = User::factory()->create();
    $this->hats->grant($owner, HatType::LlcOwner, $this->llc);

    $this->lifecycle->retireRegion($this->region);

    // The right audience: they are the people who must settle the
    // obligations before any of it fires.
    expect(Notification::query()
        ->where('user_id', $owner->id)
        ->where('kind', NotificationKind::System)
        ->exists())->toBeTrue();
});

// --- Obligations ---------------------------------------------------------

it('holds a retirement while a booking is still live', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);

    Booking::factory()->for($this->asset)->for($member)->create([
        'status' => BookingStatus::Confirmed,
    ]);

    $this->lifecycle->retireRegion($this->region);

    expect($this->offboarding->sweep()['recycled'])->toBe(0)
        ->and($this->asset->refresh()->isRecycled())->toBeFalse();
});

it('fires the retirement once nothing is outstanding', function () {
    $this->lifecycle->retireRegion($this->region);

    $swept = $this->offboarding->sweep();

    expect($swept['recycled'])->toBe(3)
        ->and($this->region->refresh()->isRecycled())->toBeTrue()
        ->and($this->asset->refresh()->isRecycled())->toBeTrue();
});

it('carries the provenance forward when it recycles', function () {
    $this->lifecycle->retireRegion($this->region);
    $this->offboarding->sweep();

    expect($this->llc->refresh()->recycled_source)->toBe(QueueSource::Region);
});

it('leaves nothing to do when the sweep runs again', function () {
    $this->lifecycle->retireRegion($this->region);
    $this->offboarding->sweep();

    expect($this->offboarding->sweep())->toBe(['removed' => 0, 'left' => 0, 'recycled' => 0]);
});

// --- Deletion ------------------------------------------------------------

it('refuses to delete a region that still has llcs', function () {
    expect(fn () => $this->lifecycle->delete($this->region))
        ->toThrow(RuntimeException::class, 'LLC(s) still belong to it');
});

it('refuses to delete over an unsettled ledger', function () {
    LedgerEntry::create([
        'owner_type' => $this->llc->getMorphClass(),
        'owner_id' => $this->llc->id,
        'direction' => LedgerDirection::Credit,
        'label' => LedgerLabel::LlcFee,
        'amount_cents' => 40_00,
        'description' => 'Fees collected and not yet paid out',
    ]);

    // The prototype's gate waited on invoices, which stopped existing when
    // billing moved to a running tally — so the money half had been dead for
    // some time.
    expect(fn () => $this->lifecycle->delete($this->llc))
        ->toThrow(RuntimeException::class, 'unsettled ledger');
});

it('lets a force delete skip the structural objections', function () {
    $this->lifecycle->forceDelete($this->region);

    expect(Region::whereKey($this->region->id)->exists())->toBeFalse();
});

it('never lets a force delete discard money', function () {
    LedgerEntry::create([
        'owner_type' => $this->llc->getMorphClass(),
        'owner_id' => $this->llc->id,
        'direction' => LedgerDirection::Credit,
        'label' => LedgerLabel::LlcFee,
        'amount_cents' => 40_00,
        'description' => 'Fees collected and not yet paid out',
    ]);

    // The prototype's force-delete skipped every check, and the check it
    // skipped was already blind to money owed.
    expect(fn () => $this->lifecycle->forceDelete($this->llc))
        ->toThrow(RuntimeException::class, 'may not discard money');
});

it('strips the hats scoped to a region it deletes', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::RegionalMember, $this->region);

    $this->lifecycle->forceDelete($this->region);

    expect($member->hats()->count())->toBe(0);
});

// --- Suspension is not a toggle ------------------------------------------

it('suspends without needing to know the current state', function () {
    $this->lifecycle->suspend($this->llc);
    $this->lifecycle->suspend($this->llc->refresh());

    // Asking twice suspends; it does not flip back. The prototype's single
    // toggle meant a caller had to know the state to predict the outcome.
    expect($this->llc->refresh()->isSuspended())->toBeTrue();
});

it('lifts a suspension and nothing else', function () {
    $this->lifecycle->retireLlc($this->llc);
    $this->lifecycle->suspend($this->llc->refresh());

    $this->lifecycle->unsuspend($this->llc->refresh());

    // The prototype's restore cleared recycled AND suspended together, so it
    // was not the inverse of either action alone.
    expect($this->llc->refresh()->isSuspended())->toBeFalse()
        ->and($this->llc->isQueuedForRetirement())->toBeTrue();
});

it('tells members when an llc is reinstated as well as suspended', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);

    $this->lifecycle->suspend($this->llc);
    $this->lifecycle->unsuspend($this->llc->refresh());

    // The prototype told them on suspension and not on release, which left
    // them believing it was still in force.
    expect(Notification::query()->where('user_id', $member->id)->count())->toBe(2);
});
