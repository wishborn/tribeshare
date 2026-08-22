<?php

namespace App\Policies;

use App\Enums\AssetPower;
use App\Enums\HatType;
use App\Models\Asset;
use App\Models\GovernanceLock;
use App\Models\User;
use App\Services\Permissions\HatService;
use App\Services\Permissions\PowerService;
use App\Services\Permissions\SuspensionService;

/**
 * Authority over an asset.
 *
 * Every ability here was enforced by disabling a button in the prototype.
 */
class AssetPolicy
{
    public function __construct(
        private readonly PowerService $powers,
        private readonly HatService $hats,
        private readonly SuspensionService $suspensions,
    ) {}

    /**
     * Seeing an asset: any standing on it, any role in its LLC, or pool
     * membership. Suspension from the owning LLC hides it.
     */
    public function view(User $user, Asset $asset): bool
    {
        if ($this->suspensions->isSuspendedFrom($user, $asset->llc)) {
            return false;
        }

        if ($user->isRcm()) {
            return true;
        }

        return $this->hats->holds($user, HatType::AssetPoolMember, $asset)
            || $this->hats->holds($user, HatType::LlcMember, $asset->llc)
            || $user->pooledAssets()->whereKey($asset->getKey())->exists();
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->powers->onAsset($user, $asset, AssetPower::EditSettings);
    }

    /**
     * Editing one particular setting.
     *
     * A field a decision froze is closed to the ordinary edit path however
     * senior the editor — the point of a lock is that an owner cannot
     * quietly reverse what the members settled. Changing it means another
     * proposal, or repealing the one that locked it.
     */
    public function updateField(User $user, Asset $asset, string $field): bool
    {
        return ! GovernanceLock::locks($asset, $field) && $this->update($user, $asset);
    }

    /**
     * Only an owner may delete, and only an owner — no delegated tier grants
     * it, so it is not in the power table at all.
     */
    public function delete(User $user, Asset $asset): bool
    {
        return $user->isRcm() || $this->hats->holds($user, HatType::AssetOwner, $asset);
    }

    public function approveBookings(User $user, Asset $asset): bool
    {
        return $this->powers->onAsset($user, $asset, AssetPower::ApproveBookings);
    }

    public function cancelBookings(User $user, Asset $asset): bool
    {
        return $this->powers->onAsset($user, $asset, AssetPower::CancelBookings);
    }

    public function buildCalendar(User $user, Asset $asset): bool
    {
        return $this->powers->onAsset($user, $asset, AssetPower::BuildCalendar);
    }

    public function publishCalendar(User $user, Asset $asset): bool
    {
        return $this->powers->onAsset($user, $asset, AssetPower::PublishCalendar);
    }

    public function managePool(User $user, Asset $asset): bool
    {
        return $this->powers->onAsset($user, $asset, AssetPower::ManagePool);
    }

    public function editPriorityList(User $user, Asset $asset): bool
    {
        return $this->powers->onAsset($user, $asset, AssetPower::EditPriorityList);
    }

    public function viewFinancials(User $user, Asset $asset): bool
    {
        return $this->powers->onAsset($user, $asset, AssetPower::ViewFinancials);
    }

    public function assignHats(User $user, Asset $asset): bool
    {
        return $this->powers->onAsset($user, $asset, AssetPower::AssignAssetHats);
    }
}
