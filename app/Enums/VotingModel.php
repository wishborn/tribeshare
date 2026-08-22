<?php

namespace App\Enums;

enum VotingModel: string
{
    /** Every eligible member counts once. */
    case OneMemberOneVote = 'one_member_one_vote';

    /** Votes carry a weight set per member. */
    case StakeWeighted = 'stake_weighted';

    /** Members may delegate; weight flows to whoever actually votes. */
    case Liquid = 'liquid';

    /** Passes when NOBODY blocks. Objection, not preference. */
    case Consent = 'consent';

    /** Members spend credits; the tally takes the square root of each spend. */
    case Quadratic = 'quadratic';

    /** Defined classes each vote separately; every class must pass. */
    case MultiStakeholder = 'multi_stakeholder';

    /**
     * Whether this model tallies yes/no/abstain with weights.
     */
    public function isWeightedBallot(): bool
    {
        return in_array($this, [
            self::OneMemberOneVote,
            self::StakeWeighted,
            self::Liquid,
        ], true);
    }

    public function usesCredits(): bool
    {
        return $this === self::Quadratic;
    }

    public function usesDelegation(): bool
    {
        return $this === self::Liquid;
    }
}
