<?php

namespace App\Console\Commands;

use App\Services\Governance\GovernanceService;
use Illuminate\Console\Command;

/**
 * The two clock-driven governance transitions: tally votes whose window has
 * expired, then apply decisions whose cooling-off has elapsed.
 *
 * Idempotent, so running it twice — or after a failure part-way — changes
 * nothing the first pass already did.
 */
class GovernanceSweepCommand extends Command
{
    protected $signature = 'tribeshare:governance-sweep';

    protected $description = 'Close finished votes and apply decisions that have come due';

    public function handle(GovernanceService $governance): int
    {
        ['closed' => $closed, 'executed' => $executed] = $governance->sweep();

        $this->info("Closed {$closed} vote(s); applied {$executed} decision(s).");

        return self::SUCCESS;
    }
}
