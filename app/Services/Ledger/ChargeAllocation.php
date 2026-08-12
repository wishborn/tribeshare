<?php

namespace App\Services\Ledger;

use App\Enums\ChargeStatus;
use App\Models\LedgerEntry;

/**
 * One charge, with how much of it has been settled and where that leaves it.
 *
 * Purely derived — never persisted.
 */
readonly class ChargeAllocation
{
    public function __construct(
        public LedgerEntry $entry,
        public int $paidCents,
        public int $remainingCents,
        public ChargeStatus $status,
    ) {}

    public function isSettled(): bool
    {
        return $this->remainingCents === 0;
    }
}
