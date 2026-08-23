<?php

namespace App\Policies;

use App\Enums\HatType;
use App\Models\Region;
use App\Models\User;
use App\Services\Permissions\HatService;

/**
 * Authority over a region.
 *
 * Regions have no delegated power table — they are the content authority's
 * own level. An **RCM** runs them; a **RegionOwner** runs theirs.
 *
 * An Admin does not appear here. Managing the platform is not managing a
 * region's assets, members or fees.
 */
class RegionPolicy
{
    public function __construct(private readonly HatService $hats) {}

    /**
     * Hidden regions are visible to the content authority only — and that is
     * an access rule, not a listing convenience.
     */
    public function view(User $user, Region $region): bool
    {
        if ($user->isRcm()) {
            return true;
        }

        if (! $region->visible) {
            return false;
        }

        return $this->hats->holds($user, HatType::RegionalMember, $region);
    }

    public function update(User $user, Region $region): bool
    {
        return $user->isRcm() || $this->hats->holds($user, HatType::RegionOwner, $region);
    }

    /**
     * Creating and retiring regions is the content authority's alone.
     */
    public function create(User $user): bool
    {
        return $user->isRcm();
    }

    public function retire(User $user, Region $region): bool
    {
        unset($region);

        return $user->isRcm();
    }

    public function delete(User $user, Region $region): bool
    {
        unset($region);

        return $user->isRcm();
    }

    public function setFee(User $user, Region $region): bool
    {
        return $user->isRcm() || $this->hats->holds($user, HatType::RegionOwner, $region);
    }

    public function manageDocuments(User $user, Region $region): bool
    {
        return $user->isRcm() || $this->hats->holds($user, HatType::RegionOwner, $region);
    }

    /**
     * Claims are insurance business, and follow the documents they arise
     * from.
     */
    public function manageClaims(User $user, Region $region): bool
    {
        return $this->manageDocuments($user, $region);
    }

    public function restore(User $user, Region $region): bool
    {
        unset($region);

        return $user->isRcm();
    }

    /**
     * Deleting over the structural objections.
     *
     * Still the content authority's, and still unable to discard money — the
     * service refuses that for everyone, so this only decides who may reach
     * for it at all.
     */
    public function forceDelete(User $user, Region $region): bool
    {
        unset($region);

        return $user->isRcm();
    }

    /**
     * Who members here may message. A region-level policy decision, so it
     * belongs to whoever runs the region.
     */
    public function setMessagingScope(User $user, Region $region): bool
    {
        return $user->isRcm() || $this->hats->holds($user, HatType::RegionOwner, $region);
    }
}
