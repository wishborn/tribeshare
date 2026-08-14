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
     * Platform operator, above the content hierarchy.
     *
     * Where an RCM administers the CONTENT — regions, LLCs, assets,
     * members — an Admin administers the platform itself. New: the
     * prototype's ceiling was the RCM.
     *
     * The FIRST Admin created is the Super Admin. That is a distinguished
     * Admin, not a separate type — the same shape as an asset's main owner,
     * where the first grant is recorded and later ones do not displace it.
     */
    case Admin = 'Admin';

    /**
     * A hat may only grant hats ranked strictly below its own.
     *
     * Rank is also what authorization compares: "at least an AssetManager
     * here", never "holds the AssetManager hat". Higher hats IMPLY the ones
     * beneath them rather than materialising them, so the hierarchy cannot
     * drift out of step with itself.
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
            self::Admin => 11,
        };
    }

    /**
     * Whether holding this hat implies holding the given one at the same
     * scope — so granting AssetOwner needs no further rows.
     */
    public function implies(self $other): bool
    {
        return $this->family() === $other->family()
            && $this->rank() >= $other->rank();
    }

    /**
     * Which hierarchy a hat belongs to. Only hats in the same family imply
     * one another: an LLC Owner is not thereby an Asset Owner.
     */
    public function family(): string
    {
        return match ($this) {
            self::AssetPoolMember, self::AssetAdmin,
            self::AssetManager, self::AssetOwner => 'asset',
            self::LlcMember, self::LlcAdmin,
            self::LlcManager, self::LlcOwner => 'llc',
            self::RegionalMember, self::RegionOwner => 'region',
            self::Rcm, self::Admin => 'platform',
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
