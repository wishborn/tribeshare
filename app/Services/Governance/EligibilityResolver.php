<?php

namespace App\Services\Governance;

use App\Enums\HatType;
use App\Models\Asset;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may vote on an entity's proposals.
 *
 * Derived, never stored.
 */
class EligibilityResolver
{
    /**
     * @return array<int, string> user ids
     */
    public function for(Model $governable): array
    {
        return match (true) {
            $governable instanceof Llc => $this->forLlc($governable),
            $governable instanceof Region => $this->forRegion($governable),
            $governable instanceof Asset => $this->forAsset($governable),
            default => [],
        };
    }

    /**
     * Anyone holding an active hat scoped to the LLC.
     *
     * @return array<int, string>
     */
    private function forLlc(Llc $llc): array
    {
        return $this->voting()
            ->scopedStrictlyTo($llc)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Members of the region itself, **and** of any LLC within it.
     *
     * The prototype looked only at hats scoped to the region's LLCs — and a
     * `Regional Member` hat is scoped to the region, so it never matched.
     * Members who belonged to the region but to no LLC were silently
     * ineligible to vote in their own region.
     *
     * @return array<int, string>
     */
    private function forRegion(Region $region): array
    {
        $llcIds = $region->llcs()->pluck('id')->all();

        $regional = $this->voting()
            ->scopedStrictlyTo($region)
            ->pluck('user_id');

        $throughLlcs = $this->voting()
            ->where('scopeable_type', (new Llc)->getMorphClass())
            ->whereIn('scopeable_id', $llcIds)
            ->pluck('user_id');

        return $regional->merge($throughLlcs)->unique()->values()->all();
    }

    /**
     * The asset's pool.
     *
     * @return array<int, string>
     */
    private function forAsset(Asset $asset): array
    {
        return $asset->poolMembers()->pluck('users.id')->all();
    }

    /**
     * Active hats that carry a vote.
     *
     * @return Builder<Hat>
     */
    private function voting(): Builder
    {
        return Hat::query()
            ->active()
            ->whereNotIn('type', array_map(
                fn (HatType $type) => $type->value,
                array_filter(HatType::cases(), $this->excludes(...)),
            ));
    }

    /**
     * An RCM facilitates rather than participates — the same principle that
     * stops them holding a booking, and it keeps a regional steward's vote
     * from swinging every LLC they oversee.
     *
     * It is the *hat* that carries no vote, not the person: someone who is
     * an RCM and also an ordinary member of an LLC votes there on the
     * strength of the membership.
     */
    public function excludes(HatType $type): bool
    {
        return in_array($type, [HatType::Rcm, HatType::Admin], true);
    }
}
