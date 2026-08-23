<?php

namespace App\Services\Permissions;

use App\Enums\HatType;
use App\Exceptions\HatChangeRefused;
use App\Models\Asset;
use App\Models\Hat;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Granting and revoking hats.
 *
 * The hierarchy is IMPLIED, not materialised: granting AssetOwner writes one
 * row, and holding it means holding everything beneath it in the same family.
 * The prototype wrote four rows per grant so demotion could be a deletion;
 * here demotion explicitly grants the next hat down instead, which cannot
 * drift out of step with itself.
 */
class HatService
{
    /**
     * Grant a hat, or refuse.
     *
     * @param  Model|null  $scope  the entity it applies to; null is the global scope
     */
    public function grant(User $user, HatType $type, ?Model $scope = null, bool $pending = false): Hat
    {
        return DB::transaction(function () use ($user, $type, $scope, $pending): Hat {
            if ($this->holds($user, $type, $scope)) {
                throw HatChangeRefused::alreadyHeld($type->value);
            }

            $hat = Hat::create([
                'user_id' => $user->id,
                'type' => $type,
                'scopeable_type' => $scope?->getMorphClass(),
                'scopeable_id' => $scope?->getKey(),
                // A pending hat exists but is inert until approved.
                'active' => ! $pending,
            ]);

            $this->applyGrantSideEffects($user, $type, $scope);

            return $hat;
        });
    }

    /**
     * Activate a hat that was created pending.
     *
     * A request creates the hat it anticipates immediately but inert, so
     * approval is a state change rather than a creation. Activating has to
     * run the same side effects a direct grant would — pool access, first
     * ownership — or an approved owner ends up outside the pool of the asset
     * they own.
     */
    public function activate(Hat $hat): Hat
    {
        return DB::transaction(function () use ($hat): Hat {
            $hat->update(['active' => true]);

            $this->applyGrantSideEffects($hat->user, $hat->type, $hat->scopeable);

            return $hat->refresh();
        });
    }

    /**
     * Revoke a hat, or refuse.
     *
     * Three refusals are absolute. They are not policy — no actor overrides
     * them, and governance executing a proposal comes through here too.
     */
    public function revoke(Hat $hat): void
    {
        DB::transaction(function () use ($hat): void {
            $this->assertNotLastMembership($hat);
            $this->assertNotSoleOwner($hat);
            $this->assertNotSuperAdmin($hat);

            $hat->delete();
        });
    }

    /**
     * Demote in one step: grant the lesser hat, then remove the greater.
     *
     * Because the hierarchy is implied, a bare revocation would drop the
     * member to nothing. This preserves the prototype's graceful-demotion
     * intent without materialising every lower hat up front.
     */
    public function demote(Hat $hat, HatType $to): Hat
    {
        return DB::transaction(function () use ($hat, $to): Hat {
            if (! $hat->type->implies($to) || $hat->type === $to) {
                throw HatChangeRefused::cannotGrantAbove($to->value);
            }

            $lesser = Hat::create([
                'user_id' => $hat->user_id,
                'type' => $to,
                'scopeable_type' => $hat->scopeable_type,
                'scopeable_id' => $hat->scopeable_id,
                'active' => true,
            ]);

            // Guards still apply — but the lesser hat now exists, so a
            // membership demotion no longer trips the last-membership check.
            $this->assertNotSoleOwner($hat);
            $hat->delete();

            return $lesser;
        });
    }

    /**
     * Whether the member holds this hat here, or one that implies it.
     *
     * A globally-scoped hat counts: holding LLCManager everywhere means
     * holding it over this LLC too. Only the two sensitive LLC powers demand
     * an exactly-scoped hat, and they ask for it explicitly.
     */
    public function holds(User $user, HatType $type, ?Model $scope = null): bool
    {
        return $user->hats()
            ->active()
            ->when(
                $scope === null,
                fn ($q) => $q->whereNull('scopeable_id'),
                fn ($q) => $q->applyingTo($scope),
            )
            ->get()
            ->contains(fn (Hat $held) => $held->type->implies($type));
    }

    /**
     * A member's standing over an entity: the highest rank among hats that
     * apply to it, globally-scoped hats included.
     */
    public function rankFor(User $user, ?Model $scope = null): int
    {
        $hats = $user->hats()->active()
            ->when($scope !== null, fn ($q) => $q->applyingTo($scope))
            ->get();

        return $hats->reduce(
            fn (int $highest, Hat $hat) => max($highest, $hat->type->rank()),
            -1,
        );
    }

    /**
     * Side effects the prototype attached to granting, kept because they are
     * genuinely part of what a grant means.
     */
    private function applyGrantSideEffects(User $user, HatType $type, ?Model $scope): void
    {
        // The first Admin created is the Super Admin, and later ones do not
        // displace them.
        if ($type === HatType::Admin && ! User::query()->where('is_super_admin', true)->exists()) {
            $user->forceFill(['is_super_admin' => true])->save();
        }

        if ($scope === null) {
            return;
        }

        // Asset standing implies pool access — you cannot manage what you
        // cannot reach.
        if ($type->family() === 'asset' && $scope instanceof Asset) {
            $scope->poolMembers()->syncWithoutDetaching([$user->id]);

            // The first owner becomes the asset's main owner, who receives
            // its income. Later owners do not displace them.
            if ($type === HatType::AssetOwner && $scope->main_owner_id === null) {
                $scope->forceFill(['main_owner_id' => $user->id])->save();
            }
        }
    }

    /**
     * A member must always belong somewhere.
     */
    private function assertNotLastMembership(Hat $hat): void
    {
        if (! $hat->type->isMembership()) {
            return;
        }

        $remaining = Hat::query()
            ->where('user_id', $hat->user_id)
            ->where('type', $hat->type)
            ->whereKeyNot($hat->getKey())
            ->active()
            ->exists();

        if (! $remaining) {
            throw HatChangeRefused::lastMembership($hat->type->value);
        }
    }

    /**
     * The Super Admin is undeletable, on the sole-owner precedent.
     *
     * HatPolicy refuses this too, but policy decides authority and this
     * decides possibility — the platform must never be left without the one
     * account that can appoint Admins.
     */
    private function assertNotSuperAdmin(Hat $hat): void
    {
        if ($hat->type === HatType::Admin && $hat->user->isSuperAdmin()) {
            throw HatChangeRefused::superAdmin();
        }
    }

    /**
     * An entity must always have someone answerable for it.
     */
    private function assertNotSoleOwner(Hat $hat): void
    {
        if (! in_array($hat->type, [HatType::LlcOwner, HatType::AssetOwner], true)) {
            return;
        }

        $others = Hat::query()
            ->where('type', $hat->type)
            ->where('scopeable_type', $hat->scopeable_type)
            ->where('scopeable_id', $hat->scopeable_id)
            ->whereKeyNot($hat->getKey())
            ->active()
            ->exists();

        if (! $others) {
            throw HatChangeRefused::soleOwner($hat->type->value);
        }
    }
}
