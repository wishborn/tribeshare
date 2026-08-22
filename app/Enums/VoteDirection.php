<?php

namespace App\Enums;

enum VoteDirection: string
{
    case Yes = 'yes';
    case No = 'no';

    /**
     * Present, taking no side.
     *
     * **Counts toward quorum only.** It drops out of the yes/no split
     * entirely — the prototype added abstentions to the denominator in its
     * shared tally, which made turning up and taking no side
     * indistinguishable from voting no, while excluding them in the
     * multi-stakeholder model. One behaviour, everywhere.
     */
    case Abstain = 'abstain';

    /** Consent model only: a reasoned objection that alone defeats a proposal. */
    case Block = 'block';

    public function countsTowardSplit(): bool
    {
        return in_array($this, [self::Yes, self::No], true);
    }
}
