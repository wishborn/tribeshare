<?php

use App\Enums\HatType;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Enums\VotingModel;
use App\Models\Asset;
use App\Models\GovernanceLock;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Governance\ProposalExecutor;
use App\Services\Permissions\HatService;

beforeEach(function () {
    $this->executor = app(ProposalExecutor::class);
    $this->hats = app(HatService::class);
    $this->llc = Llc::factory()->create(['booking_fee_pct' => 3]);
});

// --- Fees -----------------------------------------------------------------

it('applies a fee change within the cap', function () {
    $proposal = carried($this->llc, ProposalType::ChangeFee, ['fee_pct' => 7.5, 'fee_min_cents' => 250]);

    $this->executor->execute($proposal);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Executed)
        ->and((float) $this->llc->refresh()->booking_fee_pct)->toBe(7.5)
        ->and($this->llc->booking_fee_min_cents)->toBe(250);
});

it('refuses a fee a vote is not entitled to set', function () {
    $proposal = carried($this->llc, ProposalType::ChangeFee, ['fee_pct' => 40]);

    $this->executor->execute($proposal);

    // The prototype clamped governance to 100% while the ordinary path
    // clamped to 10, so a vote could set a fee four times the maximum. The
    // cap binds a vote exactly as it binds an owner.
    expect($proposal->refresh()->status)->toBe(ProposalStatus::Blocked)
        ->and($proposal->failure_reason)->toContain('10')
        ->and((float) $this->llc->refresh()->booking_fee_pct)->toBe(3.0);
});

// --- Membership: the guards ------------------------------------------------

it('adds a member a vote asked for', function () {
    $newcomer = User::factory()->create();
    $proposal = carried($this->llc, ProposalType::AddMember, ['user_id' => $newcomer->id]);

    $this->executor->execute($proposal);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Executed)
        ->and($this->hats->holds($newcomer, HatType::LlcMember, $this->llc))->toBeTrue();
});

it('removes a member who belongs elsewhere too', function () {
    $other = Llc::factory()->create();
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);
    $this->hats->grant($member, HatType::LlcMember, $other);

    $proposal = carried($this->llc, ProposalType::RemoveMember, ['user_id' => $member->id]);

    $this->executor->execute($proposal);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Executed)
        ->and($this->hats->holds($member, HatType::LlcMember, $this->llc))->toBeFalse()
        ->and($this->hats->holds($member, HatType::LlcMember, $other))->toBeTrue();
});

it('will not strip a members last membership however the vote went', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);

    $proposal = carried($this->llc, ProposalType::RemoveMember, ['user_id' => $member->id]);

    $this->executor->execute($proposal);

    // Passed the vote, refused at execution — and said so, rather than
    // silently doing nothing. The prototype wrote the removal directly and
    // escaped the guard entirely.
    expect($proposal->refresh()->status)->toBe(ProposalStatus::Blocked)
        ->and($proposal->failure_reason)->toContain('no membership')
        ->and($this->hats->holds($member, HatType::LlcMember, $this->llc))->toBeTrue();
});

it('will not leave an llc without an owner however the vote went', function () {
    $owner = User::factory()->create();
    $this->hats->grant($owner, HatType::LlcOwner, $this->llc);

    $proposal = carried($this->llc, ProposalType::RevokeHat, [
        'user_id' => $owner->id,
        'hat_type' => HatType::LlcOwner->value,
    ]);

    $this->executor->execute($proposal);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Blocked)
        ->and($proposal->failure_reason)->toContain('only active')
        ->and(Hat::query()->where('user_id', $owner->id)->exists())->toBeTrue();
});

it('revokes an owner once another stands behind them', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $this->hats->grant($first, HatType::LlcOwner, $this->llc);
    $this->hats->grant($second, HatType::LlcOwner, $this->llc);

    $proposal = carried($this->llc, ProposalType::RevokeHat, [
        'user_id' => $first->id,
        'hat_type' => HatType::LlcOwner->value,
    ]);

    $this->executor->execute($proposal);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Executed)
        ->and($this->hats->holds($first, HatType::LlcOwner, $this->llc))->toBeFalse();
});

it('leaves everything untouched when a branch refuses', function () {
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $this->llc);

    $proposal = carried($this->llc, ProposalType::RemoveMember, ['user_id' => $member->id], locksField: 'members');

    $this->executor->execute($proposal);

    // No lock is placed for a decision that never took effect.
    expect(GovernanceLock::query()->where('proposal_id', $proposal->id)->exists())->toBeFalse();
});

// --- Asset settings --------------------------------------------------------

it('changes an asset setting a proposal is allowed to touch', function () {
    $asset = Asset::factory()->create(['no_cancel_minutes' => 60]);
    $proposal = carried($asset, ProposalType::ChangeAssetSetting, ['field' => 'no_cancel_minutes', 'value' => 120]);

    $this->executor->execute($proposal);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Executed)
        ->and($asset->refresh()->no_cancel_minutes)->toBe(120);
});

it('refuses an asset setting outside the allow list', function () {
    $asset = Asset::factory()->create();
    $proposal = carried($asset, ProposalType::ChangeAssetSetting, ['field' => 'main_owner_id', 'value' => null]);

    // The prototype wrote any dotted path with no validation, so a proposal
    // could reach fields other rules constrain.
    $this->executor->execute($proposal);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Blocked)
        ->and($proposal->failure_reason)->toContain('main_owner_id');
});

// --- Governance about governance -------------------------------------------

it('changes the voting model by vote', function () {
    $proposal = carried($this->llc, ProposalType::ChangeGovernanceModel, ['model' => VotingModel::Consent->value]);

    $this->executor->execute($proposal);

    expect($proposal->refresh()->config->model)->toBe(VotingModel::Consent);
});

it('freezes the field a decision settled', function () {
    $proposal = carried($this->llc, ProposalType::ChangeFee, ['fee_pct' => 5], locksField: 'booking_fee_pct');

    $this->executor->execute($proposal);

    $lock = GovernanceLock::query()->where('proposal_id', $proposal->id)->sole();

    expect($lock->field)->toBe('booking_fee_pct')
        ->and($lock->lockable_id)->toBe($this->llc->id);
});

it('undoes a decision by repealing it', function () {
    $original = carried($this->llc, ProposalType::ChangeFee, ['fee_pct' => 5], locksField: 'booking_fee_pct');
    $this->executor->execute($original);

    $repeal = Proposal::factory()->under($original->config)->create([
        'type' => ProposalType::Repeal,
        'status' => ProposalStatus::Passed,
        'repeal_of_id' => $original->id,
        'executes_at' => now()->subMinute(),
    ]);

    $this->executor->execute($repeal);

    // Repeal is a proposal type, not the dead standalone action the
    // prototype also carried.
    expect($original->refresh()->status)->toBe(ProposalStatus::Repealed)
        ->and(GovernanceLock::query()->where('proposal_id', $original->id)->exists())->toBeFalse();
});

it('refuses a repeal that names nothing', function () {
    $proposal = carried($this->llc, ProposalType::Repeal, []);

    $this->executor->execute($proposal);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Blocked);
});
