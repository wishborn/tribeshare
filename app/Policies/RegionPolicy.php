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
}
