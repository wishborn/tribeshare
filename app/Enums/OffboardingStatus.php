<?php

namespace App\Enums;

/**
 * A grace-period queue's state.
 *
 * The prototype held these as flags and arrays on the member, which lost who
 * queued them and when. They are state machines and are modelled as such.
 */
enum OffboardingStatus: string
{
    case Queued = 'queued';
    case Cancelled = 'cancelled';
    case Fired = 'fired';

    public function isPending(): bool
    {
        return $this === self::Queued;
    }
}
