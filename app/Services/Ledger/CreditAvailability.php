<?php

namespace App\Services\Ledger;

use Illuminate\Support\Carbon;

/**
 * Credit split into what can be paid out now and what is still maturing.
 */
readonly class CreditAvailability
{
    /**
     * @param  array<int, array{date: Carbon, amount_cents: int}>  $upcoming
     */
    public function __construct(
        public int $totalCents,
        public int $availableCents,
        public array $upcoming,
    ) {}

    public function hasMaturingCredit(): bool
    {
        return $this->upcoming !== [];
    }
}
