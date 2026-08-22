<?php

use App\Enums\HatType;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Enums\VoteDirection;
use App\Enums\VotingModel;
use App\Models\GovernanceConfig;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\Proposal;
use App\Models\Region;
use App\Models\User;
use App\Services\Governance\EligibilityResolver;
use App\Services\Governance\GovernanceService;
use App\Services\Permissions\HatService;

beforeEach(function () {
    $this->governance = app(GovernanceService::class);
    $this->hats = app(HatService::class);
    $this->llc = Llc::factory()->create();
});

// --- Eligibility ----------------------------------------------------------

it('lets a regional member vote in their own region', function () {
    $region = Region::factory()->create();
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::RegionalMember, $region);

    // The prototype looked only at hats scoped to the region's LLCs, and a
    // Regional Member hat is scoped to the region — so a member belonging to
    // the region but to no LLC was silently ineligible in their own region.
    expect(app(EligibilityResolver::class)->for($region))->toContain($member->id);
});

it('also counts members reached through the regions LLCs', function () {
    $region = Region::factory()->create();
    $llc = Llc::factory()->for($region)->create();
    $member = User::factory()->create();
    $this->hats->grant($member, HatType::LlcMember, $llc);

    expect(app(EligibilityResolver::class)->for($region))->toContain($member->id);
});

// --- Petitions ------------------------------------------------------------

it('opens the vote once a petition reaches its threshold', function () {
    $voters = members($this->llc, 5);
    $config = GovernanceConfig::factory()->governing($this->llc)->create(['petition_threshold_pct' => 40]);
    $proposal = Proposal::factory()->under($config)->status(ProposalStatus::Petition)->create();

    $this->governance->sign($proposal, $voters[0]);
    expect($proposal->refresh()->status)->toBe(ProposalStatus::Petition);

    // Two of five is 40%.
    $this->governance->sign($proposal->refresh(), $voters[1]);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Voting)
        ->and($proposal->voting_closes_at)->not->toBeNull();
});

it('counts a signature once however often it is offered', function () {
    $voters = members($this->llc, 10);
    $config = GovernanceConfig::factory()->governing($this->llc)->create();
    $proposal = Proposal::factory()->under($config)->status(ProposalStatus::Petition)->create();

    $this->governance->sign($proposal, $voters[0]);
    $this->governance->sign($proposal->refresh(), $voters[0]);

    expect($proposal->signatures()->count())->toBe(1);
});

// --- Delegation -----------------------------------------------------------

it('follows a delegation chain to whoever actually votes', function () {
    [$a, $b, $c, $d] = members($this->llc, 4);
    $proposal = proposalUsing($this->llc, VotingModel::Liquid, quorum: 50, pass: 60);

    // A delegates to B, B delegates to C. Only C votes, and carries all
    // three — the prototype stranded A's weight at a B who never voted.
    $this->governance->delegate($proposal, $a, $b);
    $this->governance->delegate($proposal->refresh(), $b, $c);
    $this->governance->castVote($proposal->refresh(), $c, VoteDirection::Yes);
    $this->governance->castVote($proposal->refresh(), $d, VoteDirection::No);

    // Three weight for, one against — 75%.
    expect($this->governance->close($proposal->refresh())->status)->toBe(ProposalStatus::Passed);
});

it('surrenders a members own vote when they delegate', function () {
    [$a, $b] = members($this->llc, 2);
    $proposal = proposalUsing($this->llc, VotingModel::Liquid);

    $this->governance->castVote($proposal, $a, VoteDirection::No);
    $this->governance->delegate($proposal->refresh(), $a, $b);

    // Delegation is exclusive — the earlier vote is gone, so the same
    // preference cannot count twice in opposite directions.
    expect($proposal->votes()->where('user_id', $a->id)->exists())->toBeFalse();
});

it('revokes a delegation when the member votes after all', function () {
    [$a, $b] = members($this->llc, 2);
    $proposal = proposalUsing($this->llc, VotingModel::Liquid);

    $this->governance->delegate($proposal, $a, $b);
    $this->governance->castVote($proposal->refresh(), $a, VoteDirection::Yes);

    expect($proposal->delegations()->where('from_user_id', $a->id)->exists())->toBeFalse();
});

it('refuses a delegation that would form a cycle', function () {
    [$a, $b, $c] = members($this->llc, 3);
    $proposal = proposalUsing($this->llc, VotingModel::Liquid);

    $this->governance->delegate($proposal, $a, $b);
    $this->governance->delegate($proposal->refresh(), $b, $c);

    expect(fn () => $this->governance->delegate($proposal->refresh(), $c, $a))
        ->toThrow(InvalidArgumentException::class, 'cycle');
});

it('refuses a member delegating to themselves', function () {
    [$a] = members($this->llc, 1);
    $proposal = proposalUsing($this->llc, VotingModel::Liquid);

    expect(fn () => $this->governance->delegate($proposal, $a, $a))
        ->toThrow(InvalidArgumentException::class);
});

// --- Closing and the cooling-off ------------------------------------------

it('stamps the cooling-off delay when a proposal carries', function () {
    [$a, $b] = members($this->llc, 2);
    $config = GovernanceConfig::factory()->governing($this->llc)->create(['execution_delay_days' => 3]);
    $proposal = Proposal::factory()->under($config)->voting()->create(['execution_delay_days' => 3]);

    $this->governance->castVote($proposal, $a, VoteDirection::Yes);
    $this->governance->castVote($proposal->refresh(), $b, VoteDirection::Yes);

    $proposal->refresh();

    expect($proposal->status)->toBe(ProposalStatus::Passed)
        ->and($proposal->executes_at)->not->toBeNull()
        // The prototype applied a proposal immediately when it was executed
        // straight out of voting, honouring the delay only on the other path.
        ->and($proposal->executes_at->greaterThan(now()->addDays(2)))->toBeTrue();
});

it('does not execute before the cooling-off has elapsed', function () {
    [$a, $b] = members($this->llc, 2);
    $config = GovernanceConfig::factory()->governing($this->llc)->create();
    $proposal = Proposal::factory()->under($config)->voting()->create();

    $this->governance->castVote($proposal, $a, VoteDirection::Yes);
    $this->governance->castVote($proposal->refresh(), $b, VoteDirection::Yes);

    expect($this->governance->sweep()['executed'])->toBe(0);

    $this->travel(3)->days();

    expect($this->governance->sweep()['executed'])->toBe(1)
        ->and($proposal->refresh()->status)->toBe(ProposalStatus::Executed);
});

it('closes a vote whose window has expired', function () {
    $voters = members($this->llc, 4);
    $proposal = proposalUsing($this->llc, VotingModel::OneMemberOneVote, quorum: 50);

    $this->governance->castVote($proposal, $voters[0], VoteDirection::Yes);
    $this->governance->castVote($proposal->refresh(), $voters[1], VoteDirection::Yes);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Voting);

    $this->travel(8)->days();

    expect($this->governance->sweep()['closed'])->toBe(1)
        ->and($proposal->refresh()->status)->toBe(ProposalStatus::Passed);
});

it('leaves nothing to do when the sweep runs again', function () {
    [$a, $b] = members($this->llc, 2);
    $config = GovernanceConfig::factory()->governing($this->llc)->create();
    Proposal::factory()->under($config)->voting()->create();

    $this->travel(10)->days();
    $this->governance->sweep();

    // Idempotent — a second pass finds nothing.
    expect($this->governance->sweep())->toBe(['closed' => 0, 'executed' => 0]);
});

// --- Withdrawal -----------------------------------------------------------

it('lets only the proposer withdraw', function () {
    [$a, $b] = members($this->llc, 2);
    $config = GovernanceConfig::factory()->governing($this->llc)->create();
    $proposal = Proposal::factory()->under($config)->voting()->create(['proposed_by' => $a->id]);

    expect(fn () => $this->governance->withdraw($proposal, $b))
        ->toThrow(RuntimeException::class, 'Only the proposer');

    $this->governance->withdraw($proposal, $a);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Withdrawn);
});

it('refuses a vote on a proposal that is not open', function () {
    [$a] = members($this->llc, 1);
    $config = GovernanceConfig::factory()->governing($this->llc)->create();
    $proposal = Proposal::factory()->under($config)->status(ProposalStatus::Draft)->create();

    expect(fn () => $this->governance->castVote($proposal, $a, VoteDirection::Yes))
        ->toThrow(RuntimeException::class, 'not open for voting');
});

it('records a proposal type it can execute', function () {
    $proposer = User::factory()->create();

    $proposal = $this->governance->propose(
        $proposer,
        $this->llc,
        ProposalType::ChangeFee,
        'Raise the booking fee',
        ['fee_pct' => 7],
        ProposalStatus::Voting,
    );

    expect($proposal->status)->toBe(ProposalStatus::Voting)
        ->and($proposal->voting_closes_at)->not->toBeNull()
        ->and($proposal->action_payload['fee_pct'])->toBe(7);
});
