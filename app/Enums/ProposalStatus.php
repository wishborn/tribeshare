<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case Petition = 'petition';
    case Voting = 'voting';
    case Passed = 'passed';
    case Executed = 'executed';
    case Failed = 'failed';
    case Withdrawn = 'withdrawn';
    case Repealed = 'repealed';

    /**
     * Passed its vote but refused at execution.
     *
     * A state the prototype could not reach, because its execution wrote
     * state directly and bypassed the guards. Now that guards always win, a
     * proposal can carry the day and still be impossible to apply — and
     * saying so is better than silently doing nothing.
     */
    case Blocked = 'blocked';

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Petition, self::Voting], true);
    }

    public function isSettled(): bool
    {
        return ! $this->isOpen() && $this !== self::Passed;
    }
}
