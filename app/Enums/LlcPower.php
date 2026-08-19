<?php

namespace App\Enums;

/**
 * What a Manager or Admin may do on an LLC.
 */
enum LlcPower: string
{
    case ApproveAssets = 'approve_assets';
    case ApproveAppraisals = 'approve_appraisals';
    case ManagePools = 'manage_pools';
    case AssignHats = 'assign_hats';
    case ManageMembers = 'manage_members';
    case ClearMonthlyBookings = 'clear_monthly_bookings';
    case ManageGiveUp = 'manage_give_up';
    case ReviewSlotSuggestions = 'review_slot_suggestions';

    /**
     * Shipped defaults. Managers hold most; Admins hold the routine half,
     * with no control over members or hats.
     */
    public function grantedByDefaultTo(PowerTier $tier): bool
    {
        if ($tier === PowerTier::Manager) {
            return true;
        }

        return match ($this) {
            self::ApproveAssets,
            self::AssignHats,
            self::ManageMembers,
            self::ClearMonthlyBookings => false,
            default => true,
        };
    }

    /**
     * Powers that require a hat scoped EXACTLY to the LLC.
     *
     * A globally-scoped hat satisfies every other check but not these — the
     * two that decide who belongs and who holds authority.
     */
    public function requiresStrictScope(): bool
    {
        return in_array($this, [self::ManageMembers, self::AssignHats], true);
    }
}
