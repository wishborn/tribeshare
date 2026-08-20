<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authority over another member.
 *
 * Suspension is the content authority's: an RCM bars globally, an LLC's
 * member-managers bar within their own LLC. Deleting a member is nobody's
 * casual right.
 */
class UserPolicy
{
    public function view(User $actor, User $member): bool
    {
        return $actor->id === $member->id || $actor->isRcm() || $actor->isAdmin();
    }

    /**
     * A member edits their own profile. Nobody else edits it for them except
     * the content authority.
     */
    public function update(User $actor, User $member): bool
    {
        return $actor->id === $member->id || $actor->isRcm();
    }

    /**
     * Global suspension is the RCM's. The Super Admin can never be barred.
     */
    public function suspendGlobally(User $actor, User $member): bool
    {
        if ($member->isSuperAdmin()) {
            return false;
        }

        return $actor->isRcm() && $actor->id !== $member->id;
    }

    public function liftSuspension(User $actor, User $member): bool
    {
        unset($member);

        return $actor->isRcm();
    }

    /**
     * Billing suspension is not issued by anyone — it is derived from the
     * ledger. Nobody may set or clear it directly; settling the balance
     * clears it.
     */
    public function clearBillingSuspension(User $actor, User $member): bool
    {
        unset($actor, $member);

        return false;
    }

    public function delete(User $actor, User $member): bool
    {
        if ($member->isSuperAdmin() || $actor->id === $member->id) {
            return false;
        }

        return $actor->isRcm();
    }

    /**
     * Whether the actor may see what a member owes.
     */
    public function viewFinancials(User $actor, User $member): bool
    {
        return $actor->id === $member->id || $actor->isRcm();
    }
}
