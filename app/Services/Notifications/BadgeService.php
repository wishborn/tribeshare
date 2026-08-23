<?php

namespace App\Services\Notifications;

use App\Enums\BookingStatus;
use App\Enums\ProposalStatus;
use App\Enums\RequestStatus;
use App\Models\Booking;
use App\Models\MemberRequest;
use App\Models\Notification;
use App\Models\PageSeenCount;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Governance\EligibilityResolver;
use App\Services\Ledger\LedgerService;
use App\Services\Messaging\MessagingService;

/**
 * The sidebar's red and amber badges.
 *
 * A per-member map of counts already seen: opening a page records the count at
 * that moment, and the badge returns once the live count exceeds it. It is a
 * real feature and members will notice its absence.
 *
 * **Server-computed now.** The prototype derived every count in the browser
 * from the whole application state, which the client no longer receives.
 *
 * Two pages behave unusually, and both are configured rather than special-cased
 * in the code: `bookings` never fades, and `billing` is excluded from
 * seen-tracking entirely — a debt does not stop mattering because you looked at
 * it.
 */
class BadgeService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly MessagingService $messaging,
        private readonly EligibilityResolver $eligibility,
    ) {}

    /**
     * Every badge for a member, ready to hand to the sidebar.
     *
     * @return array<string, array{count: int, badge: bool}>
     */
    public function forMember(User $user): array
    {
        $seen = PageSeenCount::query()
            ->where('user_id', $user->id)
            ->pluck('seen_count', 'page');

        $badges = [];

        foreach ($this->pages() as $page) {
            $count = $this->countFor($user, $page);

            $badges[$page] = [
                'count' => $count,
                'badge' => $this->shouldShow($page, $count, (int) ($seen[$page] ?? 0)),
            ];
        }

        return $badges;
    }

    /**
     * Record that the member has opened a page.
     *
     * Pages excluded from seen-tracking record nothing, so their badge is
     * driven purely by the live count.
     */
    public function markSeen(User $user, string $page): void
    {
        if (! in_array($page, $this->pages(), true) || $this->isExcluded($page)) {
            return;
        }

        PageSeenCount::updateOrCreate(
            ['user_id' => $user->id, 'page' => $page],
            ['seen_count' => $this->countFor($user, $page), 'seen_at' => now()],
        );
    }

    /**
     * The live count behind one badge.
     */
    public function countFor(User $user, string $page): int
    {
        return match ($page) {
            'notifications' => Notification::query()->for($user)->unread()->count(),
            'messages' => $this->messaging->unreadCount($user),
            'requests' => $this->pendingRequestsFor($user),
            'governance' => $this->openVotesFor($user),
            'bookings' => $this->liveBookingsFor($user),
            'billing' => $this->ledger->hasOverdueCharges($user) ? 1 : 0,
            default => 0,
        };
    }

    /**
     * Whether the badge shows.
     *
     * A page that never fades shows whenever there is anything at all; every
     * other page shows only what has arrived since the member last looked.
     */
    private function shouldShow(string $page, int $count, int $seen): bool
    {
        if ($count === 0) {
            return false;
        }

        if ($this->neverFades($page) || $this->isExcluded($page)) {
            return true;
        }

        return $count > $seen;
    }

    private function liveBookingsFor(User $user): int
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->whereIn('status', BookingStatus::liveValues())
            ->count();
    }

    /**
     * Requests waiting on this member, not requests they raised.
     *
     * A badge is a call to act.
     */
    private function pendingRequestsFor(User $user): int
    {
        return MemberRequest::query()
            ->where('status', RequestStatus::Pending)
            ->where('requested_by', '!=', $user->id)
            ->get()
            ->filter(fn (MemberRequest $request) => $user->can('resolve', $request))
            ->count();
    }

    /**
     * Open votes the member is eligible for and has not yet taken part in.
     */
    private function openVotesFor(User $user): int
    {
        return Proposal::query()
            ->where('status', ProposalStatus::Voting)
            ->with('governable')
            ->get()
            ->filter(function (Proposal $proposal) use ($user): bool {
                if ($proposal->governable === null) {
                    return false;
                }

                if (! in_array($user->id, $this->eligibility->for($proposal->governable), true)) {
                    return false;
                }

                return ! $proposal->votes()->where('user_id', $user->id)->exists()
                    && ! $proposal->delegations()->where('from_user_id', $user->id)->exists();
            })
            ->count();
    }

    /**
     * @return array<int, string>
     */
    private function pages(): array
    {
        /** @var array<int, string> $pages */
        $pages = config('tribeshare.notifications.badge_pages', []);

        return $pages;
    }

    private function neverFades(string $page): bool
    {
        /** @var array<int, string> $pages */
        $pages = config('tribeshare.notifications.never_fades', []);

        return in_array($page, $pages, true);
    }

    private function isExcluded(string $page): bool
    {
        /** @var array<int, string> $pages */
        $pages = config('tribeshare.notifications.excluded_from_seen', []);

        return in_array($page, $pages, true);
    }
}
