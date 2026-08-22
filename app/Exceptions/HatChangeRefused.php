<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A hat could not be granted or revoked.
 *
 * The three revocation refusals are domain invariants, not policy checks —
 * they hold against every actor including an RCM, and governance does not
 * override them either.
 */
class HatChangeRefused extends RuntimeException
{
    public static function lastMembership(string $type): self
    {
        return new self(
            "Removing this {$type} hat would leave the member with no membership. Nobody may do that, including an RCM."
        );
    }

    public static function soleOwner(string $type): self
    {
        return new self(
            "This is the only active {$type}. Appoint another before removing this one."
        );
    }

    public static function superAdmin(): self
    {
        return new self(
            'The Super Admin cannot be stripped of the Admin hat. Hand the role on first.'
        );
    }

    public static function cannotGrantAbove(string $type): self
    {
        return new self("A hat may only grant hats ranked below its own; {$type} is not.");
    }

    public static function alreadyHeld(string $type): self
    {
        return new self("The member already holds {$type} at this scope, or a hat that implies it.");
    }
}
