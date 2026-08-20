<?php

namespace App\Policies;

use App\Enums\AssetPower;
use App\Enums\HatType;
use App\Enums\LlcPower;
use App\Models\Asset;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\Region;
use App\Models\User;
use App\Services\Permissions\HatService;
use App\Services\Permissions\PowerService;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may hand out and take away authority.
 *
 * Two rules combine: a member may only grant hats ranked **strictly below**
 * their own standing over that scope, and the scope itself must be one they
 * hold the relevant power over.
 *
 * The domains split here. An **RCM** is the content authority and controls
 * access to regional hats. An **Admin** manages the platform, and their
 * distinctive right is appointing an RCM — not running an LLC.
 */
class HatPolicy
{
    public function __construct(
        private readonly HatService $hats,
        private readonly PowerService $powers,
    ) {}

    /**
     * May the actor grant this hat at this scope?
     */
    public function grant(User $actor, HatType $type, ?Model $scope = null): bool
    {
        // Nobody appoints an Admin but the Super Admin — including other
        // Admins, and including the RCM.
        if ($type === HatType::Admin) {
            return $actor->isSuperAdmin();
        }

        // Only an Admin appoints the content authority.
        if ($type === HatType::Rcm) {
            return $actor->isAdmin();
        }

        // You may only grant below your own standing.
        if ($this->hats->rankFor($actor, $scope) <= $type->rank()) {
            return false;
        }

        return match ($type->family()) {
            'region' => $this->mayGrantRegionHat($actor),
            'llc' => $scope instanceof Llc && $this->powers->onLlc($actor, $scope, LlcPower::AssignHats),
            'asset' => $scope instanceof Asset && $this->mayGrantAssetHat($actor, $scope),
            default => false,
        };
    }

    /**
     * May the actor revoke this hat?
     *
     * The two invariants are NOT checked here — they live in HatService and
     * throw for everyone, including an RCM and including governance. This
     * decides authority; the service decides possibility.
     */
    public function revoke(User $actor, Hat $hat): bool
    {
        $scope = $hat->scopeable_id === null ? null : $hat->scopeable;

        // The Super Admin cannot be stripped of the platform, by anyone.
        if ($hat->type === HatType::Admin && $hat->user->isSuperAdmin()) {
            return false;
        }

        return $this->grant($actor, $hat->type, $scope instanceof Model ? $scope : null);
    }

    /**
     * Regional hats are the RCM's remit — that is the job.
     *
     * An Admin may not hand out regional membership; they appoint the RCM
     * who does.
     */
    private function mayGrantRegionHat(User $actor): bool
    {
        return $actor->isRcm();
    }

    private function mayGrantAssetHat(User $actor, Asset $asset): bool
    {
        // Assigning asset hats is owner-only: the power is false for both
        // delegated tiers, so only an owner (or the RCM) satisfies it.
        return $this->powers->onAsset($actor, $asset, AssetPower::AssignAssetHats);
    }

    /**
     * Regions have no delegated power table of their own, so membership of
     * one is granted by the content authority.
     */
    public function grantRegionalMembership(User $actor, Region $region): bool
    {
        unset($region);

        return $actor->isRcm();
    }
}
