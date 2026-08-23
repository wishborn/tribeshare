<?php

use App\Enums\HatType;
use App\Enums\NotificationKind;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Asset;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\MemberRequest;
use App\Models\Notification;
use App\Models\Region;
use App\Models\User;
use App\Services\Notifications\BadgeService;
use App\Services\Permissions\HatService;
use App\Services\Requests\RequestService;

beforeEach(function () {
    $this->requests = app(RequestService::class);
    $this->hats = app(HatService::class);
    $this->region = Region::factory()->create();
    $this->llc = Llc::factory()->for($this->region)->create();

    $this->owner = User::factory()->create();
    $this->hats->grant($this->owner, HatType::LlcOwner, $this->llc);

    $this->member = User::factory()->create();
    $this->hats->grant($this->member, HatType::LlcMember, $this->llc);
});

// --- Pending hats ------------------------------------------------------------

it('creates the hat a request implies, inactive until approval', function () {
    $newcomer = User::factory()->create();

    $request = $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    // The intended end state exists from the start, so approving is a state
    // change rather than a creation.
    expect($request->pendingHat)->not->toBeNull()
        ->and($request->pendingHat->active)->toBeFalse()
        ->and($this->hats->holds($newcomer, HatType::LlcMember, $this->llc))->toBeFalse();
});

it('activates the pending hat on approval', function () {
    $newcomer = User::factory()->create();
    $request = $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    $this->requests->approve($request, $this->owner);

    expect($this->hats->holds($newcomer, HatType::LlcMember, $this->llc))->toBeTrue()
        ->and($request->refresh()->status)->toBe(RequestStatus::Approved);
});

it('deletes the pending hat on denial', function () {
    $newcomer = User::factory()->create();
    $request = $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);
    $hatId = $request->pending_hat_id;

    $this->requests->deny($request, $this->owner, 'Not this time.');

    expect(Hat::whereKey($hatId)->exists())->toBeFalse()
        ->and($request->refresh()->status)->toBe(RequestStatus::Denied)
        ->and($request->resolution_note)->toBe('Not this time.');
});

it('gives an approved pool member the access their hat implies', function () {
    $asset = Asset::factory()->for($this->llc)->create();
    $newcomer = User::factory()->create();

    $request = $this->requests->raise($newcomer, RequestType::JoinPool, $asset);
    $this->requests->approve($request, $this->owner);

    // Activating runs the same side effects a direct grant would, or an
    // approved member ends up outside the pool they just joined.
    expect($asset->refresh()->poolMembers()->whereKey($newcomer->id)->exists())->toBeTrue();
});

// --- One resolution path -----------------------------------------------------

it('approves an asset submission fully when resolved on its own', function () {
    $asset = Asset::factory()->for($this->llc)->create(['approved_at' => null]);
    $submitter = User::factory()->create();

    $request = $this->requests->raise($submitter, RequestType::AddAsset, $asset);

    $this->requests->approve($request, $this->owner);

    // The prototype's single-resolution path updated the request's own status
    // and nothing else, so an asset approved one at a time was never actually
    // approved. Batch resolution did the whole job; the two have one
    // implementation now.
    expect($asset->refresh()->approved_at)->not->toBeNull()
        ->and($this->hats->holds($submitter, HatType::AssetOwner, $asset))->toBeTrue();
});

it('approves the same way in a batch as it does singly', function () {
    $first = Asset::factory()->for($this->llc)->create(['approved_at' => null]);
    $second = Asset::factory()->for($this->llc)->create(['approved_at' => null]);

    $a = User::factory()->create();
    $b = User::factory()->create();

    $requests = [
        $this->requests->raise($a, RequestType::AddAsset, $first),
        $this->requests->raise($b, RequestType::AddAsset, $second),
    ];

    $this->requests->approveMany($requests, $this->owner);

    expect($first->refresh()->approved_at)->not->toBeNull()
        ->and($second->refresh()->approved_at)->not->toBeNull()
        ->and($this->hats->holds($a, HatType::AssetOwner, $first))->toBeTrue()
        ->and($this->hats->holds($b, HatType::AssetOwner, $second))->toBeTrue();
});

it('recycles the asset when a submission is denied', function () {
    $asset = Asset::factory()->for($this->llc)->create(['approved_at' => null]);
    $submitter = User::factory()->create();

    $request = $this->requests->raise($submitter, RequestType::AddAsset, $asset);
    $this->requests->deny($request, $this->owner);

    expect($asset->refresh()->isRecycled())->toBeTrue();
});

// --- Withdrawal ---------------------------------------------------------------

it('gives a member their work back when they withdraw a submission', function () {
    $asset = Asset::factory()->for($this->llc)->create(['approved_at' => null]);
    $submitter = User::factory()->create();

    $request = $this->requests->raise($submitter, RequestType::AddAsset, $asset);
    $this->requests->withdraw($request, $submitter);

    // Back to draft rather than recycled — withdrawing is not the same as
    // being turned down.
    expect($request->refresh()->status)->toBe(RequestStatus::Withdrawn)
        ->and($asset->refresh()->isRecycled())->toBeFalse()
        ->and($asset->approved_at)->toBeNull()
        ->and(Hat::where('user_id', $submitter->id)->exists())->toBeFalse();
});

it('lets only the requester withdraw', function () {
    $newcomer = User::factory()->create();
    $request = $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    expect(fn () => $this->requests->withdraw($request, $this->owner))
        ->toThrow(RuntimeException::class, 'Only the member who raised');
});

it('refuses to resolve a request twice', function () {
    $newcomer = User::factory()->create();
    $request = $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    $this->requests->approve($request, $this->owner);

    expect(fn () => $this->requests->deny($request->fresh(), $this->owner))
        ->toThrow(RuntimeException::class, 'already been resolved');
});

it('refuses a second request for the same thing', function () {
    $newcomer = User::factory()->create();
    $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    expect(fn () => $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc))
        ->toThrow(RuntimeException::class, 'already have that request');
});

it('refuses a request for a role already held', function () {
    expect(fn () => $this->requests->raise($this->member, RequestType::JoinLlc, $this->llc))
        ->toThrow(RuntimeException::class, 'already hold that role');
});

// --- Cap overrides store intent ------------------------------------------------

it('stores the intent of a cap override, never a formed booking', function () {
    $asset = Asset::factory()->for($this->llc)->create();

    $request = $this->requests->raise(
        $this->member,
        RequestType::CapOverride,
        $asset,
        'Family reunion.',
        ['starts_at' => now()->addWeek()->toIso8601String(), 'mesos' => 40],
    );

    // The prototype stored a complete booking with its ledger entries and
    // replayed them verbatim on approval, which computed money on the client
    // and froze the price at request time.
    expect($request->payload)->toHaveKeys(['starts_at', 'mesos'])
        ->and($request->payload)->not->toHaveKey('ledger_entries')
        ->and($request->payload)->not->toHaveKey('total_cents')
        ->and($request->pendingHat)->toBeNull();
});

// --- Who may resolve -------------------------------------------------------------

it('lets an owner resolve a request on their llc', function () {
    $newcomer = User::factory()->create();
    $request = $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    expect($this->owner->can('resolve', $request))->toBeTrue()
        ->and($this->member->can('resolve', $request))->toBeFalse();
});

it('never lets a member resolve their own request', function () {
    $asset = Asset::factory()->for($this->llc)->create();

    // The owner asks to join a pool on an asset in the LLC they own.
    $request = $this->requests->raise($this->owner, RequestType::JoinPool, $asset);

    // Approving your own request is not a decision, however senior you are.
    expect($this->owner->fresh()->can('resolve', $request))->toBeFalse();
});

it('lets an rcm resolve anything', function () {
    $rcm = User::factory()->create();
    $this->hats->grant($rcm, HatType::Rcm, $this->region);

    $newcomer = User::factory()->create();
    $request = $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    expect($rcm->can('resolve', $request))->toBeTrue();
});

// --- Notifications ---------------------------------------------------------------

it('tells the approvers a request is waiting', function () {
    $newcomer = User::factory()->create();
    $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    expect(Notification::query()
        ->where('user_id', $this->owner->id)
        ->where('kind', NotificationKind::Request)
        ->exists())->toBeTrue();
});

it('tells the requester the outcome', function () {
    $newcomer = User::factory()->create();
    $request = $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    $this->requests->approve($request, $this->owner);

    expect(Notification::query()->where('user_id', $newcomer->id)->get()->pluck('title'))
        ->toContain('LLC join request approved');
});

it('lists a pending request against the member who must decide it', function () {
    $newcomer = User::factory()->create();
    $this->requests->raise($newcomer, RequestType::JoinLlc, $this->llc);

    expect(MemberRequest::query()->pending()->count())->toBe(1)
        ->and(app(BadgeService::class)->countFor($this->owner, 'requests'))->toBe(1)
        // A badge is a call to act, so it does not count what you raised
        // yourself.
        ->and(app(BadgeService::class)->countFor($newcomer, 'requests'))->toBe(0);
});
