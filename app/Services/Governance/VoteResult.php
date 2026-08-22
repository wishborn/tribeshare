<?php

namespace App\Services\Governance;

use App\Enums\VotingModel;

/**
 * What a tally concluded.
 */
readonly class VoteResult
{
    /**
     * @param  array<int, array{name: string, passed: bool, quorumMet: bool, yesPct: float}>  $classResults
     */
    public function __construct(
        public bool $passed,
        public VotingModel $model,
        public int $eligible,
        public int $participated,
        public bool $quorumMet,
        public float $yesPct = 0.0,
        public int $blocks = 0,
        public array $classResults = [],
    ) {}
}
