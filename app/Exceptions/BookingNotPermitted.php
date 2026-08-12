<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking was refused by a domain rule.
 *
 * Every one of these is a rule the prototype enforced only by disabling a
 * button, so a direct API call bypassed it.
 */
class BookingNotPermitted extends RuntimeException
{
    public static function rcmMayNotBook(): self
    {
        return new self('An RCM facilitates bookings but never holds one.');
    }

    public static function assetFrozenForRetirement(): self
    {
        return new self('This asset, its LLC or its region is queued for retirement.');
    }

    public static function billingSuspended(): self
    {
        return new self('Booking is suspended until the overdue balance is settled.');
    }

    public static function wouldExceedBalanceCap(): self
    {
        return new self('This booking would take the member past their carried balance limit.');
    }

    public static function notInPool(): self
    {
        return new self('The member has no access to this asset.');
    }

    public static function slotTaken(): self
    {
        return new self('That time is already booked.');
    }

    public static function mayNotBump(): self
    {
        return new self('The member does not outrank the existing booking.');
    }

    public static function invalidRange(): self
    {
        return new self('A booking must end after it starts.');
    }
}
