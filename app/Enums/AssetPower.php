<?php

namespace App\Enums;

/**
 * What a Manager or Admin may do on an asset.
 */
enum AssetPower: string
{
    case ApproveBookings = 'approve_bookings';
    case CancelBookings = 'cancel_bookings';
    case BuildCalendar = 'build_calendar';
    case PublishCalendar = 'publish_calendar';
    case EditSettings = 'edit_settings';
    case ManagePool = 'manage_pool';
    case EditPriorityList = 'edit_priority_list';
    case ClearMonthlyBookings = 'clear_monthly_bookings';
    case AutoBook = 'auto_book';
    case ViewFinancials = 'view_financials';
    case ManageGiveUp = 'manage_give_up';
    case AssignAssetHats = 'assign_asset_hats';

    /**
     * Shipped defaults.
     *
     * Assigning asset hats is false for BOTH tiers — it is owner-only, and
     * the entry exists to say so rather than to be toggled.
     */
    public function grantedByDefaultTo(PowerTier $tier): bool
    {
        if ($this === self::AssignAssetHats) {
            return false;
        }

        if ($tier === PowerTier::Manager) {
            return true;
        }

        return match ($this) {
            self::PublishCalendar,
            self::EditSettings,
            self::ClearMonthlyBookings,
            self::AutoBook,
            self::ManageGiveUp => false,
            default => true,
        };
    }
}
