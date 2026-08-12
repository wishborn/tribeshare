<?php

namespace App\Enums;

/**
 * Scoped roles AND memberships — the prototype conflates the two and ranks
 * them on one ladder, so they share an enum.
 */
enum HatType: string
{
    case RegionalMember = 'Regional Member';
    case LlcMember = 'LLC Member';
    case AssetPoolMember = 'Asset Pool Member';
    case AssetAdmin = 'AssetAdmin';
    case AssetManager = 'AssetManager';
    case AssetOwner = 'AssetOwner';
    case LlcAdmin = 'LLCAdmin';
    case LlcManager = 'LLCManager';
    case LlcOwner = 'LLCOwner';
    case RegionOwner = 'RegionOwner';
    case Rcm = 'RCM';

    /**
     * A hat may only grant hats ranked strictly below its own.
     *
     * NOTE: the prototype omits RegionOwner from its ranking table entirely
     * while still addressing notifications to it, so it had no rank. It is
     * given one here, between LLCOwner and RCM, which is where its authority
     * sits. Confirm during the permissions pass.
     */
    public function rank(): int
    {
        return match ($this) {
            self::RegionalMember => 0,
            self::LlcMember => 1,
            self::AssetPoolMember => 2,
            self::AssetAdmin => 3,
            self::AssetManager => 4,
            self::AssetOwner => 5,
            self::LlcAdmin => 6,
            self::LlcManager => 7,
            self::LlcOwner => 8,
            self::RegionOwner => 9,
            self::Rcm => 10,
        };
    }

    public function isMembership(): bool
    {
        return in_array($this, [
            self::RegionalMember,
            self::LlcMember,
            self::AssetPoolMember,
        ], true);
    }

    /**
     * Booking priority, which decides who may bump whom.
     *
     * Uses DIRECT asset hats only — there is deliberately no LLC cascade
     * here, so an LLC Owner without an asset hat books as a pool member.
     */
    public function bookingPriority(): int
    {
        return match ($this) {
            self::Rcm => 5,
            self::AssetOwner => 4,
            self::AssetManager => 3,
            self::AssetAdmin => 2,
            default => 1,
        };
    }
}
