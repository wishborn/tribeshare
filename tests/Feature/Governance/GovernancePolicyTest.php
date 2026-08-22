<?php

use App\Enums\HatType;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Models\Asset;
use App\Models\GovernanceConfig;
use App\Models\Llc;
use App\Models\Proposal;
use App\Models\Region;
use App\Models\User;
use App\Services\Governance\EligibilityResolver;
use App\Services\Governance\ProposalExecutor;
use App\Services\Permissions\HatService;
use App\Services\Permissions\SuspensionService;

beforeEach(function () {
    $this->hats = app(HatService::class);
    $this->llc = Llc::factory()->create();
    $this->config = GovernanceConfig::factory()->governing($this->llc)->create(['enabled' => true]);
});

// --- Who may raise a proposal ---------------------------------------------

it('lets an owner propose when proposing belongs to owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $this->hats->grant($owner, HatType::LlcOwner, $this->llc);
    $this->hats->grant($member, HatType::LlcMember, $this->llc);

    expect($owner->can('create', [Proposal::class, $this->config]))->toBeTrue()
        ->and($member->can('create', [Proposal::class, $this->config]))->toBeFalse();
});

it('lets any member propose when the llc opens it that wide', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);
    $this->config->update(['who_can_propose' => 'members']);

    expect($member->can('create', [Proposal::class, $this->config]))->toBeTrue();
});

it('lets a member propose on an explicit grant alone', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);

    expect($member->can('create', [Proposal::class, $this->config]))->toBeFalse();

    // Granted the right without being made an owner — the escape hatch a
    // small LLC needs.
    $this->config->proposalRights()->attach($member->id);

    expect($member->fresh()->can('create', [Proposal::class, $this->config->fresh()]))->toBeTrue();
});

it('refuses an outsider whatever the setting says', function () {
    $stranger = User::factory()->create();
    $this->config->update(['who_can_propose' => 'anyone']);

    // "Anyone" means anyone eligible, not anyone at all.
    expect($stranger->can('create', [Proposal::class, $this->config]))->toBeFalse();
});

it('refuses everyone while governance is switched off', function () {
    $owner = User::factory()->create();
    $this->hats->grant($owner, HatType::LlcOwner, $this->llc);
    $this->config->update(['enabled' => false]);

    expect($owner->can('create', [Proposal::class, $this->config]))->toBeFalse();
});

// --- Voting and signing ----------------------------------------------------

it('refuses a suspended member the vote they would otherwise have', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);
    $proposal = Proposal::factory()->under($this->config)->voting()->create();

    expect($member->can('vote', $proposal))->toBeTrue();

    app(SuspensionService::class)->suspendFrom($member, $this->llc, note: 'Unpaid dues.');

    // Still on the roll — the quorum still divides by them — but they may
    // not act while suspended.
    expect($member->fresh()->can('vote', $proposal))->toBeFalse()
        ->and(app(EligibilityResolver::class)->for($this->llc))->toContain($member->id);
});

it('refuses a vote once the proposal has been decided', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);
    $proposal = Proposal::factory()->under($this->config)->status(ProposalStatus::Passed)->create();

    expect($member->can('vote', $proposal))->toBeFalse();
});

it('refuses a signature when petitions are switched off', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);
    $this->config->update(['petition_enabled' => false]);
    $proposal = Proposal::factory()->under($this->config)->status(ProposalStatus::Petition)->create();

    expect($member->can('sign', $proposal))->toBeFalse();
});

// --- The RCM facilitates ---------------------------------------------------

it('gives an rcm no vote on the strength of the rcm hat', function () {
    $region = Region::factory()->create();
    $rcm = User::factory()->create();
    $this->hats->grant($rcm, HatType::Rcm, $region);

    // The same principle that stops them holding a booking: a regional
    // steward's vote should not swing every LLC they oversee.
    expect(app(EligibilityResolver::class)->for($region))->not->toContain($rcm->id);
});

it('gives an rcm the vote their ordinary membership carries', function () {
    $region = Region::factory()->create();
    $llc = Llc::factory()->for($region)->create();
    $rcm = User::factory()->create();
    $this->hats->grant($rcm, HatType::Rcm, $region);
    $this->hats->grant($rcm, HatType::LlcMember, $llc);

    // It is the hat that carries no vote, not the person.
    expect(app(EligibilityResolver::class)->for($llc))->toContain($rcm->id);
});

// --- Locks -----------------------------------------------------------------

it('closes a locked field to the ordinary edit path', function () {
    $owner = User::factory()->create();
    $this->hats->grant($owner, HatType::LlcOwner, $this->llc);

    expect($owner->can('setFee', $this->llc))->toBeTrue();

    $proposal = carried($this->llc, ProposalType::ChangeFee, ['fee_pct' => 5], locksField: 'booking_fee_pct');
    app(ProposalExecutor::class)->execute($proposal);

    // The prototype recorded locks and never consulted them, so a lock was
    // decoration and an owner could reverse a decision the next morning.
    expect($owner->fresh()->can('setFee', $this->llc->fresh()))->toBeFalse();
});

it('leaves fields the decision did not name alone', function () {
    $asset = Asset::factory()->create();
    $owner = User::factory()->create();
    $this->hats->grant($owner, HatType::AssetOwner, $asset);

    $proposal = carried($asset, ProposalType::ChangeAssetSetting,
        ['field' => 'no_cancel_minutes', 'value' => 90], locksField: 'no_cancel_minutes');
    app(ProposalExecutor::class)->execute($proposal);

    expect($owner->can('updateField', [$asset, 'no_cancel_minutes']))->toBeFalse()
        ->and($owner->can('updateField', [$asset, 'max_group_size']))->toBeTrue();
});

it('reopens a locked field when the decision is repealed', function () {
    $owner = User::factory()->create();
    $this->hats->grant($owner, HatType::LlcOwner, $this->llc);

    $original = carried($this->llc, ProposalType::ChangeFee, ['fee_pct' => 5], locksField: 'booking_fee_pct');
    app(ProposalExecutor::class)->execute($original);

    $repeal = Proposal::factory()->under($this->config)->create([
        'type' => ProposalType::Repeal,
        'status' => ProposalStatus::Passed,
        'repeal_of_id' => $original->id,
        'executes_at' => now()->subMinute(),
    ]);
    app(ProposalExecutor::class)->execute($repeal);

    // A decision is undone the same way it was made.
    expect($owner->fresh()->can('setFee', $this->llc->fresh()))->toBeTrue();
});
