<?php

namespace App\Policies;

use App\Enums\HatType;
use App\Enums\LlcPower;
use App\Models\GovernanceLock;
use App\Models\Llc;
use App\Models\User;
use App\Services\Permissions\HatService;
use App\Services\Permissions\PowerService;
use App\Services\Permissions\SuspensionService;

/**
 * Authority over an LLC.
 *
 * Note what is absent: nothing here consults asset standing. The prototype
 * granted LLC-wide delegated powers to anyone holding an asset-level hat on
 * any one asset in it; that escalation path is not reproduced.
 */
class LlcPolicy
{
    public function __construct(
        private readonly PowerService $powers,
        private readonly HatService $hats,
        private readonly SuspensionService $suspensions,
    ) {}

    public function view(User $user, Llc $llc): bool
    {
        if ($this->suspensions->isSuspendedFrom($user, $llc)) {
            return false;
        }

        return $user->isRcm() || $this->hats->holds($user, HatType::LlcMember, $llc);
    }

    /**
     * Only an owner may reshape the LLC itself.
     */
    public function update(User $user, Llc $llc): bool
    {
        return $user->isRcm() || $this->hats->holds($user, HatType::LlcOwner, $llc);
    }

    public function delete(User $user, Llc $llc): bool
    {
        return $user->isRcm() || $this->hats->holds($user, HatType::LlcOwner, $llc);
    }

    public function manageMembers(User $user, Llc $llc): bool
    {
        return $this->powers->onLlc($user, $llc, LlcPower::ManageMembers);
    }

    public function assignHats(User $user, Llc $llc): bool
    {
        return $this->powers->onLlc($user, $llc, LlcPower::AssignHats);
    }

    public function approveAssets(User $user, Llc $llc): bool
    {
        return $this->powers->onLlc($user, $llc, LlcPower::ApproveAssets);
    }

    public function managePools(User $user, Llc $llc): bool
    {
        return $this->powers->onLlc($user, $llc, LlcPower::ManagePools);
    }

    public function approveAppraisals(User $user, Llc $llc): bool
    {
        return $this->powers->onLlc($user, $llc, LlcPower::ApproveAppraisals);
    }

    public function clearMonthlyBookings(User $user, Llc $llc): bool
    {
        return $this->powers->onLlc($user, $llc, LlcPower::ClearMonthlyBookings);
    }

    public function reviewSlotSuggestions(User $user, Llc $llc): bool
    {
        return $this->powers->onLlc($user, $llc, LlcPower::ReviewSlotSuggestions);
    }

    /**
     * Setting the LLC's booking fee is an ownership decision — and it is
     * capped, wherever it is set from, including by a governance vote.
     */
    public function setFee(User $user, Llc $llc): bool
    {
        if (GovernanceLock::locks($llc, 'booking_fee_pct')) {
            // A vote settled the fee and froze it. Only another proposal, or
            // a repeal of the one that locked it, moves it now.
            return false;
        }

        return $user->isRcm() || $this->hats->holds($user, HatType::LlcOwner, $llc);
    }

    /**
     * Editing one particular field, refused while a decision holds it frozen.
     */
    public function updateField(User $user, Llc $llc, string $field): bool
    {
        return ! GovernanceLock::locks($llc, $field) && $this->update($user, $llc);
    }
}
