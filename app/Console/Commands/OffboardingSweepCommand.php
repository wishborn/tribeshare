<?php

namespace App\Console\Commands;

use App\Services\Organisation\OffboardingService;
use Illuminate\Console\Command;

/**
 * The three grace-period queues: member removals, LLC departures and
 * retirements that have come clear.
 *
 * Idempotent, so running it twice changes nothing the first pass already did.
 * It also runs after any booking status change, so settling the last booking
 * fires a queue at once rather than waiting for the next tick.
 */
class OffboardingSweepCommand extends Command
{
    protected $signature = 'tribeshare:offboarding-sweep';

    protected $description = 'Fire member removals, LLC departures and retirements whose obligations have settled';

    public function handle(OffboardingService $offboarding): int
    {
        ['removed' => $removed, 'left' => $left, 'recycled' => $recycled] = $offboarding->sweep();

        $this->info("Removed {$removed} member(s); {$left} departure(s); recycled {$recycled} entity(ies).");

        return self::SUCCESS;
    }
}
