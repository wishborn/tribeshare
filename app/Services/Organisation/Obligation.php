<?php

namespace App\Services\Organisation;

/**
 * One reason a departure or retirement cannot yet complete.
 *
 * A value rather than a boolean, because "you cannot leave yet" is useless on
 * its own — the member needs to know what to settle.
 */
readonly class Obligation
{
    public function __construct(
        public string $kind,
        public string $summary,
        public int $count = 0,
        public int $amountCents = 0,
    ) {}
}
