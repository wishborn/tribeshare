<?php

namespace App\Services\Organisation;

use App\Enums\BookingStatus;
use App\Enums\PayoutStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Llc;
use App\Models\Region;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use Illuminate\Database\Eloquent\Model;

/**
 * What has to settle before a departure or retirement completes.
 *
 * **Decided: bookings and money both.** The prototype counted open bookings
 * only, so a member could be recycled owing for a stay they never paid for,
 * or lose a credit balance nobody ever returned to them. The ledger knows
 * about outstanding charges, unsettled credit and pending payouts; none were
 * consulted.
 *
 * Each check is switchable in config, because what counts as an obligation is
 * a policy an operator may reasonably want to loosen — but every one is on by
 * default.
 */
class ObligationService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Everything standing between this member and their removal.
     *
     * @return array<int, Obligation>
     */
    public function forMember(User $user): array
    {
        $obligations = [];

        if ($this->checks('open_bookings')) {
            $live = Booking::query()
                ->where('user_id', $user->id)
                ->whereIn('status', BookingStatus::liveValues())
                ->count();

            if ($live > 0) {
                $obligations[] = new Obligation(
                    'open_bookings',
                    "{$live} booking(s) still to run or return.",
                    count: $live,
                );
            }
        }

        if ($this->checks('outstanding_charges')) {
            $owed = $this->ledger->netBalanceCents($user);

            if ($owed > 0) {
                $obligations[] = new Obligation(
                    'outstanding_charges',
                    'An unpaid balance.',
                    amountCents: $owed,
                );
            }
        }

        // Credit runs the other way: the member is owed, and firing the
        // removal would quietly confiscate it.
        if ($this->checks('unsettled_credit')) {
            $credit = $this->ledger->creditBalanceCents($user);

            if ($credit > 0) {
                $obligations[] = new Obligation(
                    'unsettled_credit',
                    'Credit still held on account, which would be lost.',
                    amountCents: $credit,
                );
            }
        }

        if ($this->checks('pending_payouts')) {
            $pending = $user->payoutRequests()
                ->where('status', PayoutStatus::Pending)
                ->count();

            if ($pending > 0) {
                $obligations[] = new Obligation(
                    'pending_payouts',
                    "{$pending} payout request(s) awaiting a decision.",
                    count: $pending,
                );
            }
        }

        return $obligations;
    }

    /**
     * Everything standing between a member and leaving one LLC.
     *
     * Narrower than leaving the platform: only bookings on that LLC's assets.
     * Money is not divisible by LLC — the ledger is one running tally per
     * member — so an unpaid balance blocks leaving anywhere.
     *
     * @return array<int, Obligation>
     */
    public function forMemberLeaving(User $user, Llc $llc): array
    {
        $obligations = [];

        if ($this->checks('open_bookings')) {
            $live = Booking::query()
                ->where('user_id', $user->id)
                ->whereIn('status', BookingStatus::liveValues())
                ->whereHas('asset', fn ($q) => $q->where('llc_id', $llc->id))
                ->count();

            if ($live > 0) {
                $obligations[] = new Obligation(
                    'open_bookings',
                    "{$live} booking(s) here still to run.",
                    count: $live,
                );
            }
        }

        if ($this->checks('outstanding_charges')) {
            $owed = $this->ledger->netBalanceCents($user);

            if ($owed > 0) {
                $obligations[] = new Obligation(
                    'outstanding_charges',
                    'An unpaid balance.',
                    amountCents: $owed,
                );
            }
        }

        return $obligations;
    }

    /**
     * Everything standing between an entity and its retirement.
     *
     * @return array<int, Obligation>
     */
    public function forEntity(Model $entity): array
    {
        $obligations = [];
        $assetIds = $this->assetIdsUnder($entity);

        if ($this->checks('open_bookings') && $assetIds !== []) {
            $live = Booking::query()
                ->whereIn('asset_id', $assetIds)
                ->whereIn('status', BookingStatus::liveValues())
                ->count();

            if ($live > 0) {
                $obligations[] = new Obligation(
                    'open_bookings',
                    "{$live} booking(s) still to run on assets here.",
                    count: $live,
                );
            }
        }

        // An entity's own ledger: fees collected and not paid out, or costs
        // incurred. Retiring on top of either loses the record of whose money
        // it was.
        if ($this->checks('outstanding_charges') && ! $entity instanceof Asset) {
            $balance = $this->ledger->entityBalance($entity);

            if ($balance['net_cents'] !== 0) {
                $obligations[] = new Obligation(
                    'entity_ledger',
                    'An unsettled ledger.',
                    amountCents: abs($balance['net_cents']),
                );
            }
        }

        return $obligations;
    }

    /**
     * Whether everything is settled — the question the sweep actually asks.
     */
    public function memberIsClear(User $user): bool
    {
        return $this->forMember($user) === [];
    }

    public function memberIsClearOf(User $user, Llc $llc): bool
    {
        return $this->forMemberLeaving($user, $llc) === [];
    }

    public function entityIsClear(Model $entity): bool
    {
        return $this->forEntity($entity) === [];
    }

    /**
     * Every asset beneath an entity, itself included when it is one.
     *
     * @return array<int, string>
     */
    private function assetIdsUnder(Model $entity): array
    {
        return match (true) {
            $entity instanceof Asset => [$entity->id],
            $entity instanceof Llc => Asset::query()->where('llc_id', $entity->id)->pluck('id')->all(),
            $entity instanceof Region => Asset::query()
                ->whereIn('llc_id', Llc::query()->where('region_id', $entity->id)->select('id'))
                ->pluck('id')->all(),
            default => [],
        };
    }

    private function checks(string $obligation): bool
    {
        return (bool) config("tribeshare.offboarding.obligations.{$obligation}", true);
    }
}
