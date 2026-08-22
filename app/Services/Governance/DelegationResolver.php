<?php

namespace App\Services\Governance;

use App\Models\Proposal;
use App\Models\ProposalDelegation;
use InvalidArgumentException;

/**
 * Where a delegated vote actually lands.
 *
 * Delegation here is **transitive** and **exclusive**:
 *
 *  - If A delegates to B and B delegates to C, A's weight reaches C. The
 *    prototype stopped at one hop, stranding A's weight at a B who was not
 *    voting — which is proxy voting, not liquid democracy.
 *  - Delegating surrenders your own vote. The prototype let a delegator vote
 *    *and* lend their weight, so one preference counted twice, possibly in
 *    opposite directions.
 *  - Cycles are refused when the delegation is made, rather than silently
 *    stranding everyone in the loop.
 */
class DelegationResolver
{
    /**
     * Follow the chain from a member to whoever finally casts a vote.
     *
     * Returns null when the chain ends at somebody who never voted — which
     * contributes nothing, exactly as abstaining would.
     *
     * @param  array<string, string>  $edges  from => to
     * @param  array<string, bool>  $voters  who actually cast a vote
     */
    public function endpointFor(string $userId, array $edges, array $voters): ?string
    {
        $seen = [];
        $current = $userId;

        while (isset($edges[$current])) {
            if (isset($seen[$current])) {
                // Defensive: a cycle should have been refused at delegation
                // time, but a chain that loops contributes nothing rather
                // than looping forever.
                return null;
            }

            $seen[$current] = true;
            $current = $edges[$current];
        }

        return isset($voters[$current]) ? $current : null;
    }

    /**
     * How much extra weight each voter carries from people who delegated to
     * them, directly or through a chain.
     *
     * @param  array<string, float>  $ownWeights  member => their own weight
     * @return array<string, float> voter => delegated weight received
     */
    public function delegatedWeights(Proposal $proposal, array $ownWeights): array
    {
        $edges = $proposal->delegations
            ->mapWithKeys(fn (ProposalDelegation $d) => [$d->from_user_id => $d->to_user_id])
            ->all();

        $voters = $proposal->votes->mapWithKeys(fn ($vote) => [$vote->user_id => true])->all();

        $received = [];

        foreach (array_keys($edges) as $delegator) {
            // A delegator who also voted is not counted twice — casting a
            // vote revokes the delegation (see GovernanceService::castVote).
            if (isset($voters[$delegator])) {
                continue;
            }

            $endpoint = $this->endpointFor($delegator, $edges, $voters);

            if ($endpoint === null) {
                continue;
            }

            $received[$endpoint] = ($received[$endpoint] ?? 0) + ($ownWeights[$delegator] ?? 1.0);
        }

        return $received;
    }

    /**
     * Refuse a delegation that would close a loop.
     *
     * @param  array<string, string>  $edges  existing from => to
     */
    public function assertNoCycle(string $from, string $to, array $edges): void
    {
        if ($from === $to) {
            throw new InvalidArgumentException('A member cannot delegate to themselves.');
        }

        $seen = [];
        $current = $to;

        while (isset($edges[$current])) {
            if ($current === $from || isset($seen[$current])) {
                throw new InvalidArgumentException('That delegation would form a cycle.');
            }

            $seen[$current] = true;
            $current = $edges[$current];
        }

        if ($current === $from) {
            throw new InvalidArgumentException('That delegation would form a cycle.');
        }
    }
}
