<?php

namespace App\Services\Governance;

use App\Enums\VoteDirection;
use App\Enums\VotingModel;
use App\Models\Proposal;
use App\Models\ProposalCreditSpend;
use App\Models\ProposalVote;
use App\Models\StakeholderClass;

/**
 * Counting the vote, across all six models.
 *
 * One rule holds everywhere and did not in the prototype: **an abstention
 * counts toward quorum and drops out of the yes/no split.** The shared tally
 * used to add abstentions to the denominator, making "I showed up and take
 * no side" indistinguishable from a no, while the multi-stakeholder model
 * excluded them. Now they behave the same way in every model.
 */
class VoteTally
{
    public function __construct(private readonly DelegationResolver $delegations) {}

    /**
     * @param  array<int, string>  $eligibleUserIds
     */
    public function for(Proposal $proposal, array $eligibleUserIds): VoteResult
    {
        $config = $proposal->config;

        return match ($config->model) {
            VotingModel::Consent => $this->tallyConsent($proposal, $eligibleUserIds),
            VotingModel::Quadratic => $this->tallyQuadratic($proposal, $eligibleUserIds),
            VotingModel::MultiStakeholder => $this->tallyMultiStakeholder($proposal, $eligibleUserIds),
            default => $this->tallyWeighted($proposal, $eligibleUserIds),
        };
    }

    /**
     * One-member-one-vote, stake-weighted and liquid all share this. Only
     * where the weight comes from differs.
     *
     * @param  array<int, string>  $eligibleUserIds
     */
    private function tallyWeighted(Proposal $proposal, array $eligibleUserIds): VoteResult
    {
        $config = $proposal->config;
        $votes = $proposal->votes;

        $weights = $votes->mapWithKeys(fn (ProposalVote $v) => [$v->user_id => $v->weight])->all();

        if ($config->model->usesDelegation()) {
            $delegated = $this->delegations->delegatedWeights($proposal, $this->ownWeights($proposal, $eligibleUserIds));

            foreach ($delegated as $voterId => $extra) {
                $weights[$voterId] = ($weights[$voterId] ?? 0) + $extra;
            }
        }

        $yes = 0.0;
        $no = 0.0;

        foreach ($votes as $vote) {
            // Abstentions count for quorum but never for the split.
            if (! $vote->direction->countsTowardSplit()) {
                continue;
            }

            $weight = $weights[$vote->user_id] ?? $vote->weight;

            $vote->direction === VoteDirection::Yes
                ? $yes += $weight
                : $no += $weight;
        }

        $decisive = $yes + $no;
        $yesPct = $decisive > 0.0 ? $yes / $decisive * 100 : 0.0;

        // Participation counts people, not weight — a delegate carrying five
        // votes is still one member turning up. Delegators count too: handing
        // your vote on is participating.
        $participated = $this->participantCount($proposal);
        $quorumMet = $this->quorumMet($participated, count($eligibleUserIds), $config->quorum_pct);

        return new VoteResult(
            passed: $quorumMet && $yesPct >= $config->pass_pct,
            model: $config->model,
            eligible: count($eligibleUserIds),
            participated: $participated,
            quorumMet: $quorumMet,
            yesPct: $yesPct,
        );
    }

    /**
     * Consent: passes when nobody blocks.
     *
     * @param  array<int, string>  $eligibleUserIds
     */
    private function tallyConsent(Proposal $proposal, array $eligibleUserIds): VoteResult
    {
        $config = $proposal->config;
        $blocks = $proposal->votes->where('direction', VoteDirection::Block)->count();
        $participated = $proposal->votes->count();
        $quorumMet = $this->quorumMet($participated, count($eligibleUserIds), $config->quorum_pct);

        return new VoteResult(
            passed: $blocks === 0 && $quorumMet,
            model: $config->model,
            eligible: count($eligibleUserIds),
            participated: $participated,
            quorumMet: $quorumMet,
            blocks: $blocks,
        );
    }

    /**
     * Quadratic: weight is the square root of credits spent.
     *
     * @param  array<int, string>  $eligibleUserIds
     */
    private function tallyQuadratic(Proposal $proposal, array $eligibleUserIds): VoteResult
    {
        $config = $proposal->config;
        $spends = $proposal->creditSpends;

        $yes = $spends->where('direction', VoteDirection::Yes)
            ->reduce(fn (float $sum, ProposalCreditSpend $s) => $sum + $s->weight(), 0.0);
        $no = $spends->where('direction', VoteDirection::No)
            ->reduce(fn (float $sum, ProposalCreditSpend $s) => $sum + $s->weight(), 0.0);

        $decisive = $yes + $no;
        $yesPct = $decisive > 0.0 ? $yes / $decisive * 100 : 0.0;
        $participated = $spends->count();
        $quorumMet = $this->quorumMet($participated, count($eligibleUserIds), $config->quorum_pct);

        return new VoteResult(
            passed: $quorumMet && $yesPct >= $config->pass_pct,
            model: $config->model,
            eligible: count($eligibleUserIds),
            participated: $participated,
            quorumMet: $quorumMet,
            yesPct: $yesPct,
        );
    }

    /**
     * Multi-stakeholder: every class must carry on its own thresholds.
     *
     * @param  array<int, string>  $eligibleUserIds
     */
    private function tallyMultiStakeholder(Proposal $proposal, array $eligibleUserIds): VoteResult
    {
        $config = $proposal->config;
        $classes = $config->stakeholderClasses()->with('members')->get();

        if ($classes->isEmpty()) {
            return new VoteResult(
                passed: false,
                model: $config->model,
                eligible: count($eligibleUserIds),
                participated: 0,
                quorumMet: false,
            );
        }

        $results = [];
        $allPassed = true;

        foreach ($classes as $class) {
            $result = $this->tallyClass($proposal, $class, $config->quorum_pct, $config->pass_pct);
            $results[] = $result;
            $allPassed = $allPassed && $result['passed'];
        }

        return new VoteResult(
            passed: $allPassed,
            model: $config->model,
            eligible: count($eligibleUserIds),
            participated: $proposal->votes->count(),
            quorumMet: $allPassed,
            classResults: $results,
        );
    }

    /**
     * @return array{name: string, passed: bool, quorumMet: bool, yesPct: float}
     */
    private function tallyClass(Proposal $proposal, StakeholderClass $class, float $defaultQuorum, float $defaultPass): array
    {
        $memberIds = $class->members->pluck('id')->all();
        $votes = $proposal->votes->whereIn('user_id', $memberIds);

        $yes = $votes->where('direction', VoteDirection::Yes)->count();
        $no = $votes->where('direction', VoteDirection::No)->count();
        $decisive = $yes + $no;

        $yesPct = $decisive > 0 ? $yes / $decisive * 100 : 0.0;
        $quorumMet = $this->quorumMet($votes->count(), count($memberIds), $class->quorum_pct ?? $defaultQuorum);

        return [
            'name' => $class->name,
            'passed' => $quorumMet && $yesPct >= ($class->pass_pct ?? $defaultPass),
            'quorumMet' => $quorumMet,
            'yesPct' => $yesPct,
        ];
    }

    /**
     * Everyone who took part, whether by voting or by handing their vote on.
     */
    private function participantCount(Proposal $proposal): int
    {
        $voters = $proposal->votes->pluck('user_id')->all();
        $delegators = $proposal->delegations->pluck('from_user_id')->all();

        return count(array_unique([...$voters, ...$delegators]));
    }

    private function quorumMet(int $participated, int $eligible, float $quorumPct): bool
    {
        // Nobody eligible means nothing to reach — treated as met rather
        // than dividing by zero.
        return $eligible === 0 || ($participated / $eligible * 100) >= $quorumPct;
    }

    /**
     * @param  array<int, string>  $eligibleUserIds
     * @return array<string, float>
     */
    private function ownWeights(Proposal $proposal, array $eligibleUserIds): array
    {
        $weights = [];

        foreach ($eligibleUserIds as $id) {
            $weights[$id] = 1.0;
        }

        foreach ($proposal->votes as $vote) {
            $weights[$vote->user_id] = $vote->weight;
        }

        return $weights;
    }
}
