<?php

namespace App\Services\Messaging;

use App\Enums\MessagingScope;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\Region;
use App\Models\User;

/**
 * Whether one member may open a conversation with another.
 *
 * **Enforced server-side, and per region.** The prototype held a single
 * platform-wide setting that the reducer never consulted: the only
 * enforcement lived in the messages page, so a direct call reached anybody
 * regardless of the configured policy. The same presentation-level pattern
 * as bookings, permissions and suspension.
 *
 * The setting now sits on the region, because every value it takes is a
 * scoped concept. A region that has never chosen inherits the platform
 * default.
 *
 * Scope is checked on **creation and on send**, because a region can tighten
 * its policy after a conversation exists — and a thread that was legitimate
 * yesterday should not become a standing exemption.
 */
class MessagingScopeResolver
{
    /**
     * May the sender start or continue a conversation with this member?
     *
     * The strictest applicable policy wins. Two members may share one region
     * on generous terms and another on tight ones; the tight one governs,
     * because the alternative is that joining a permissive region silently
     * unlocks messaging everywhere else.
     */
    public function mayMessage(User $sender, User $recipient): bool
    {
        if ($sender->id === $recipient->id) {
            return true;
        }

        // An RCM has to be reachable, and has to be able to reach members:
        // their whole role is fielding the things members cannot resolve
        // among themselves.
        if ($sender->isRcm() || $recipient->isRcm()) {
            return true;
        }

        $scopes = $this->scopesBetween($sender, $recipient);

        if ($scopes === []) {
            // No shared region at all. Only a platform default of "anyone"
            // permits it.
            return $this->platformDefault() === MessagingScope::Anyone;
        }

        foreach ($scopes as $scope) {
            if (! $this->satisfies($scope, $sender, $recipient)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Everyone a member is allowed to start a conversation with.
     *
     * @return array<int, string> user ids
     */
    public function reachableBy(User $sender): array
    {
        return User::query()
            ->whereKeyNot($sender->id)
            ->get()
            ->filter(fn (User $other) => $this->mayMessage($sender, $other))
            ->pluck('id')
            ->all();
    }

    /**
     * Why a conversation was refused, for a message worth reading.
     */
    public function refusalReason(User $sender, User $recipient): string
    {
        $scopes = $this->scopesBetween($sender, $recipient);
        $scope = $scopes[0] ?? $this->platformDefault();

        return match ($scope) {
            MessagingScope::LlcOnly => 'You can only message members of an LLC you belong to.',
            MessagingScope::PoolOnly => 'You can only message members of an asset pool you share.',
            MessagingScope::Regional => 'You can only message members of your own region.',
            MessagingScope::Anyone => 'That member cannot be messaged.',
        };
    }

    private function satisfies(MessagingScope $scope, User $sender, User $recipient): bool
    {
        return match ($scope) {
            MessagingScope::Anyone => true,
            MessagingScope::Regional => $this->shareRegion($sender, $recipient),
            MessagingScope::LlcOnly => $this->shareLlc($sender, $recipient),
            MessagingScope::PoolOnly => $this->sharePool($sender, $recipient),
        };
    }

    /**
     * The policies governing this pair — one per region they have in common.
     *
     * @return array<int, MessagingScope>
     */
    private function scopesBetween(User $sender, User $recipient): array
    {
        $shared = array_intersect(
            $this->regionIdsFor($sender),
            $this->regionIdsFor($recipient),
        );

        if ($shared === []) {
            return [];
        }

        return Region::query()
            ->whereIn('id', $shared)
            ->get()
            ->map(fn (Region $region) => $region->messagingScope())
            ->unique()
            ->values()
            ->all();
    }

    private function shareRegion(User $sender, User $recipient): bool
    {
        return array_intersect($this->regionIdsFor($sender), $this->regionIdsFor($recipient)) !== [];
    }

    private function shareLlc(User $sender, User $recipient): bool
    {
        return array_intersect($this->llcIdsFor($sender), $this->llcIdsFor($recipient)) !== [];
    }

    private function sharePool(User $sender, User $recipient): bool
    {
        $mine = $sender->pooledAssets()->pluck('assets.id')->all();

        return $recipient->pooledAssets()->whereIn('assets.id', $mine)->exists();
    }

    /**
     * Regions the member belongs to — directly, or through an LLC.
     *
     * @return array<int, string>
     */
    private function regionIdsFor(User $user): array
    {
        $direct = Hat::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('scopeable_type', (new Region)->getMorphClass())
            ->pluck('scopeable_id');

        $throughLlcs = Llc::query()
            ->whereIn('id', $this->llcIdsFor($user))
            ->pluck('region_id');

        return $direct->merge($throughLlcs)->filter()->unique()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    private function llcIdsFor(User $user): array
    {
        return Hat::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('scopeable_type', (new Llc)->getMorphClass())
            ->pluck('scopeable_id')
            ->unique()
            ->values()
            ->all();
    }

    private function platformDefault(): MessagingScope
    {
        return MessagingScope::from((string) config('tribeshare.messaging.default_scope'));
    }
}
