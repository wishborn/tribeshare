<?php

use App\Enums\ProposalStatus;
use App\Enums\VoteDirection;
use App\Enums\VotingModel;
use App\Models\GovernanceConfig;
use App\Models\Llc;
use App\Models\Proposal;
use App\Models\StakeholderClass;
use App\Services\Governance\GovernanceService;
use App\Services\Permissions\HatService;

beforeEach(function () {
    $this->governance = app(GovernanceService::class);
    $this->hats = app(HatService::class);
    $this->llc = Llc::factory()->create();
});

it('carries a simple vote that meets quorum and the threshold', function () {
    [$a, $b, $c, $d] = members($this->llc, 4);
    $proposal = proposalUsing($this->llc, VotingModel::OneMemberOneVote);

    $this->governance->castVote($proposal, $a, VoteDirection::Yes);
    $this->governance->castVote($proposal, $b, VoteDirection::Yes);
    $this->governance->castVote($proposal, $c, VoteDirection::No);
    $this->governance->castVote($proposal, $d, VoteDirection::Yes);

    // Everyone voted, so it closes early: 3 of 4 in favour is 75%.
    expect($proposal->refresh()->status)->toBe(ProposalStatus::Passed);
});

it('fails a vote that misses quorum however favourable it is', function () {
    $voters = members($this->llc, 10);
    $proposal = proposalUsing($this->llc, VotingModel::OneMemberOneVote, quorum: 50);

    // Two of ten, both in favour — unanimous and still short.
    $this->governance->castVote($proposal, $voters[0], VoteDirection::Yes);
    $this->governance->castVote($proposal, $voters[1], VoteDirection::Yes);

    expect($this->governance->close($proposal->refresh())->status)->toBe(ProposalStatus::Failed);
});

it('counts an abstention toward quorum but not against the proposal', function () {
    [$a, $b, $c, $d] = members($this->llc, 4);
    $proposal = proposalUsing($this->llc, VotingModel::OneMemberOneVote, quorum: 75, pass: 60);

    $this->governance->castVote($proposal, $a, VoteDirection::Yes);
    $this->governance->castVote($proposal, $b, VoteDirection::Yes);
    $this->governance->castVote($proposal, $c, VoteDirection::Abstain);
    $this->governance->castVote($proposal, $d, VoteDirection::Abstain);

    // Quorum needs 3 of 4 and four turned up. The split is 2-0, not 2-2 —
    // the prototype counted abstentions in the denominator, which made
    // turning up and taking no side indistinguishable from voting no.
    expect($proposal->refresh()->status)->toBe(ProposalStatus::Passed);
});

// --- Consent --------------------------------------------------------------

it('passes a consent vote when nobody blocks', function () {
    [$a, $b] = members($this->llc, 2);
    $proposal = proposalUsing($this->llc, VotingModel::Consent);

    $this->governance->castVote($proposal, $a, VoteDirection::Yes);
    $this->governance->castVote($proposal, $b, VoteDirection::Abstain);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Passed);
});

it('defeats a consent vote on a single block', function () {
    $voters = members($this->llc, 5);
    $proposal = proposalUsing($this->llc, VotingModel::Consent);

    foreach (array_slice($voters, 0, 4) as $voter) {
        $this->governance->castVote($proposal, $voter, VoteDirection::Yes);
    }

    // One objection is enough, however many are in favour.
    $this->governance->castVote($proposal->refresh(), $voters[4], VoteDirection::Block, blockReason: 'Unsafe.');

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Failed);
});

// --- Quadratic ------------------------------------------------------------

it('weights a quadratic vote by the square root of the spend', function () {
    [$a, $b] = members($this->llc, 2);
    $proposal = proposalUsing($this->llc, VotingModel::Quadratic, pass: 60);

    // 100 credits against 25: four times the cost buys twice the say, so
    // 10 versus 5 — 66%, which carries.
    $this->governance->spendCredits($proposal, $a, 100, VoteDirection::Yes);
    $this->governance->spendCredits($proposal->refresh(), $b, 25, VoteDirection::No);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Passed);
});

it('spends quadratic credits from a budget that runs down', function () {
    [$a] = members($this->llc, 1);
    $config = GovernanceConfig::factory()->governing($this->llc)->using(VotingModel::Quadratic)->create();
    $first = Proposal::factory()->under($config)->voting()->create();
    $second = Proposal::factory()->under($config)->voting()->create();

    $this->governance->spendCredits($first, $a, 100, VoteDirection::Yes);

    // The allowance is 100 across proposals, not per proposal — the
    // prototype reset it for each one, which removed the scarcity the
    // mechanism depends on.
    expect(fn () => $this->governance->spendCredits($second, $a, 50, VoteDirection::Yes))
        ->toThrow(RuntimeException::class, 'only 0 remain');
});

it('returns credits when a member changes their spend', function () {
    // Two members, so the first spend does not settle the proposal outright.
    [$a] = members($this->llc, 2);
    $config = GovernanceConfig::factory()->governing($this->llc)->using(VotingModel::Quadratic)->create();
    $proposal = Proposal::factory()->under($config)->voting()->create();

    $this->governance->spendCredits($proposal, $a, 100, VoteDirection::Yes);
    $this->governance->spendCredits($proposal->refresh(), $a, 20, VoteDirection::Yes);

    expect($this->governance->creditBalanceFor($config, $a)->credits_remaining)->toBe(80);
});

// --- Settling ---------------------------------------------------------------

it('settles a vote once everyone eligible has taken part', function () {
    [$a, $b] = members($this->llc, 2);
    $proposal = proposalUsing($this->llc, VotingModel::OneMemberOneVote);

    $this->governance->castVote($proposal, $a, VoteDirection::Yes);

    // Still open — one member has yet to be heard from.
    expect($proposal->refresh()->status)->toBe(ProposalStatus::Voting);

    $this->governance->castVote($proposal, $b, VoteDirection::Yes);

    // Full turnout settles it rather than idling out the window. The cost is
    // that nobody can revise afterwards, which is why the cooling-off delay
    // runs before anything is applied.
    expect($proposal->refresh()->status)->toBe(ProposalStatus::Passed)
        ->and(fn () => $this->governance->castVote($proposal->refresh(), $a, VoteDirection::No))
        ->toThrow(RuntimeException::class, 'not open for voting');
});

it('counts a delegated member as having taken part', function () {
    [$a, $b] = members($this->llc, 2);
    $proposal = proposalUsing($this->llc, VotingModel::Liquid);

    $this->governance->delegate($proposal, $a, $b);
    $this->governance->castVote($proposal->refresh(), $b, VoteDirection::Yes);

    // A handed their vote over rather than staying silent, so the LLC has
    // been fully heard and the vote settles.
    expect($proposal->refresh()->status)->toBe(ProposalStatus::Passed);
});

// --- Multi-stakeholder ----------------------------------------------------

it('requires every stakeholder class to carry', function () {
    $config = GovernanceConfig::factory()->governing($this->llc)
        ->using(VotingModel::MultiStakeholder)->thresholds(50, 60)->create();

    [$a, $b, $c, $d] = members($this->llc, 4);

    $owners = StakeholderClass::create(['governance_config_id' => $config->id, 'name' => 'Owners']);
    $users = StakeholderClass::create(['governance_config_id' => $config->id, 'name' => 'Users']);
    $owners->members()->attach([$a->id, $b->id]);
    $users->members()->attach([$c->id, $d->id]);

    $proposal = Proposal::factory()->under($config)->voting()->create();

    // Owners in favour, users against — one class fails, so it all fails.
    $this->governance->castVote($proposal, $a, VoteDirection::Yes);
    $this->governance->castVote($proposal, $b, VoteDirection::Yes);
    $this->governance->castVote($proposal, $c, VoteDirection::No);
    $this->governance->castVote($proposal, $d, VoteDirection::No);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Failed);
});
