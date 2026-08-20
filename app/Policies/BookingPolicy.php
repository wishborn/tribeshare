<?php

namespace App\Policies;

use App\Enums\AssetPower;
use App\Models\Booking;
use App\Models\User;
use App\Services\Permissions\PowerService;
use App\Services\Permissions\SuspensionService;

/**
 * Authority over a booking.
 *
 * Whether a booking may be *created* is decided by BookingService, which
 * owns the transaction, the lock and the occupancy check. This governs what
 * may be done to one that exists.
 */
class BookingPolicy
{
    public function __construct(
        private readonly PowerService $powers,
        private readonly SuspensionService $suspensions,
    ) {}

    public function view(User $user, Booking $booking): bool
    {
        if ($booking->user_id === $user->id) {
            return true;
        }

        if ($this->suspensions->isSuspendedFrom($user, $booking->llc)) {
            return false;
        }

        return $this->powers->onAsset($user, $booking->asset, AssetPower::ApproveBookings);
    }

    public function approve(User $user, Booking $booking): bool
    {
        // Nobody approves their own booking into existence — an asset
        // manager's own bookings confirm on creation instead.
        if ($booking->user_id === $user->id) {
            return false;
        }

        return $this->powers->onAsset($user, $booking->asset, AssetPower::ApproveBookings);
    }

    public function deny(User $user, Booking $booking): bool
    {
        return $this->approve($user, $booking);
    }

    /**
     * A member may always cancel their own; a manager may cancel anyone's.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id
            || $this->powers->onAsset($user, $booking->asset, AssetPower::CancelBookings);
    }

    /**
     * Offering up is the holder's alone — it is their booking to give away.
     */
    public function offerUp(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    /**
     * Picking up requires access to the asset and a clean billing record —
     * taking on someone's booking means taking on its charge.
     */
    public function pickUp(User $user, Booking $booking): bool
    {
        if ($booking->user_id === $user->id) {
            return false;
        }

        if ($user->isRcm()) {
            return false;
        }

        if ($this->suspensions->isBillingSuspended($user)) {
            return false;
        }

        return $user->pooledAssets()->whereKey($booking->asset_id)->exists();
    }

    /**
     * Submitting metered usage is the holder's; reviewing it is the asset's.
     */
    public function submitUnitReport(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    public function reviewUnitReport(User $user, Booking $booking): bool
    {
        return $this->powers->onAsset($user, $booking->asset, AssetPower::ApproveBookings);
    }
}
