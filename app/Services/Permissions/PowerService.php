<?php

namespace App\Services\Permissions;

use App\Enums\AssetPower;
use App\Enums\HatType;
use App\Enums\LlcPower;
use App\Enums\PowerTier;
use App\Models\Asset;
use App\Models\DelegatedPower;
use App\Models\Llc;
use App\Models\User;

/**
 * Whether a member holds a delegated power over an entity.
 *
 * Owners always hold everything. Managers and Admins hold what the entity's
 * table grants them, falling back to the shipped default when it has no
 * opinion.
 *
 * **There is no cascade in either direction.** LLC roles confer no asset
 * powers, and asset roles confer no LLC powers. The prototype had the
 * latter — an asset-level hat on any ONE asset granted that LLC's delegated
 * powers across the WHOLE LLC — which was judged unintended and is not
 * reproduced.
 */
class PowerService
{
    public function __construct(private readonly HatService $hats) {}

    public function onAsset(User $user, Asset $asset, AssetPower $power): bool
    {
        if ($this->isPlatformOperator($user)) {
            return true;
        }

        if ($this->hats->holds($user, HatType::AssetOwner, $asset)) {
            return true;
        }

        $tiers = [
            [HatType::AssetManager, PowerTier::Manager],
            [HatType::AssetAdmin, PowerTier::Admin],
        ];

        foreach ($tiers as [$hat, $tier]) {
            if ($this->hats->holds($user, $hat, $asset) && $this->granted($asset, $tier, $power->value, $power->grantedByDefaultTo($tier))) {
                return true;
            }
        }

        return false;
    }

    public function onLlc(User $user, Llc $llc, LlcPower $power): bool
    {
        if ($this->isPlatformOperator($user)) {
            return true;
        }

        if ($this->hats->holds($user, HatType::LlcOwner, $llc)) {
            return true;
        }

        // The two sensitive powers demand a hat scoped exactly to this LLC —
        // a globally-scoped hat satisfies every other check but not these.
        $strict = $power->requiresStrictScope();

        $tiers = [
            [HatType::LlcManager, PowerTier::Manager],
            [HatType::LlcAdmin, PowerTier::Admin],
        ];

        foreach ($tiers as [$hat, $tier]) {
            $held = $strict
                ? $user->hasHat($hat, $llc, strict: true)
                : $this->hats->holds($user, $hat, $llc);

            if ($held && $this->granted($llc, $tier, $power->value, $power->grantedByDefaultTo($tier))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Override an entity's table for one tier and power.
     */
    public function set(Asset|Llc $entity, PowerTier $tier, AssetPower|LlcPower $power, bool $granted): DelegatedPower
    {
        return DelegatedPower::updateOrCreate(
            [
                'powerable_type' => $entity->getMorphClass(),
                'powerable_id' => $entity->getKey(),
                'tier' => $tier,
                'power' => $power->value,
            ],
            ['granted' => $granted],
        );
    }

    /**
     * An RCM administers the content; an Admin administers the platform.
     * Both short-circuit every delegated-power check.
     */
    private function isPlatformOperator(User $user): bool
    {
        return $user->isRcm() || $this->hats->holds($user, HatType::Admin);
    }

    private function granted(Asset|Llc $entity, PowerTier $tier, string $power, bool $default): bool
    {
        $override = DelegatedPower::query()
            ->where('powerable_type', $entity->getMorphClass())
            ->where('powerable_id', $entity->getKey())
            ->where('tier', $tier)
            ->where('power', $power)
            ->first();

        // No row means the entity has no opinion, so the shipped default
        // applies.
        return $override === null ? $default : $override->granted;
    }
}
