<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\HatType;
use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use App\Exceptions\BookingNotPermitted;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\CollectionItem;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Pricing\BookingPricing;
use App\Services\Pricing\PricingService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * Create a booking, or refuse it.
     *
     * Everything happens inside one transaction holding a row lock on the
     * ASSET. That serialises writes per asset — which is the right
     * granularity, and the fix for the prototype's single global lock that
     * made every member in the system contend with every other.
     */
    public function book(
        User $user,
        Asset $asset,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $basePriceCents,
        float $multiplierPct = 0.0,
        ?string $slotType = null,
        bool $requiresApproval = false,
        bool $allowBump = false,
        ?CollectionItem $item = null,
    ): Booking {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw BookingNotPermitted::invalidRange();
        }

        return DB::transaction(function () use (
            $user, $asset, $startsAt, $endsAt, $basePriceCents,
            $multiplierPct, $slotType, $requiresApproval, $allowBump, $item
        ): Booking {
            // Serialise every write against this asset. Held for the whole
            // transaction, so the overlap check below cannot race.
            $asset = Asset::query()
                ->whereKey($asset->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $asset->load('llc.region');

            $this->assertMayBook($user, $asset);

            $pricing = $this->pricing->price($asset, $basePriceCents, $multiplierPct);

            if ($this->ledger->wouldExceedCap($user, $pricing->totalCents)) {
                throw BookingNotPermitted::wouldExceedBalanceCap();
            }

            $priority = $user->bookingPriorityFor($asset);

            // The asset's turnaround buffers widen what this booking ties
            // up. Conflicts are judged on the occupied range, not the
            // booked one.
            $bookends = $asset->bookendMesos();
            $mesoMinutes = (int) config('tribeshare.bookings.meso_minutes');
            $occupiesFrom = $startsAt->copy()->subMinutes($bookends['before'] * $mesoMinutes);
            $occupiesUntil = $endsAt->copy()->addMinutes($bookends['after'] * $mesoMinutes);

            $conflicts = $this->conflictsFor($asset, $occupiesFrom, $occupiesUntil, $item);

            $bumped = $this->resolveConflicts(
                $conflicts, $user, $priority, $allowBump, $this->capacityOf($item)
            );

            $booking = Booking::create([
                'asset_id' => $asset->id,
                'user_id' => $user->id,
                'llc_id' => $asset->llc_id,
                'collection_item_id' => $item?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'occupies_from' => $occupiesFrom,
                'occupies_until' => $occupiesUntil,
                'bookend_before_mesos' => $bookends['before'],
                'bookend_after_mesos' => $bookends['after'],
                'duration_mesos' => $this->mesosBetween($startsAt, $endsAt),
                'status' => $this->initialStatus($user, $asset, $requiresApproval),
                'priority' => $priority,
                'slot_type' => $slotType,
                'bullied' => $bumped,
                ...$pricing->toBookingAttributes(),
            ]);

            $this->postBookingEntries($booking, $asset, $pricing);

            return $booking;
        });
    }

    /**
     * The six entries a booking posts. They balance exactly: what the booker
     * is debited equals what the owner, LLC and region are credited.
     */
    public function postBookingEntries(Booking $booking, Asset $asset, BookingPricing $pricing): void
    {
        $split = $this->pricing->contributionSplit($asset, $pricing->perPersonCents);

        $dueAt = now()->addDays((int) config('tribeshare.billing.due_days'));
        $context = [
            'booking_id' => $booking->id,
            'asset_id' => $asset->id,
            'due_at' => $dueAt,
        ];

        $label = $asset->name.' ('.$booking->starts_at->toDateString().')';

        // --- Debits: what the booker owes -------------------------------
        $this->ledger->post(
            $booking->user, LedgerDirection::Debit, LedgerLabel::AssetCharge,
            $pricing->perPersonCents, [...$context, 'description' => $label],
        );

        if ($pricing->llcFeeCents > 0) {
            $this->ledger->post(
                $booking->user, LedgerDirection::Debit, LedgerLabel::LlcFee,
                $pricing->llcFeeCents, [...$context, 'description' => 'LLC fee — '.$label],
            );
        }

        if ($pricing->regionFeeCents > 0) {
            $this->ledger->post(
                $booking->user, LedgerDirection::Debit, LedgerLabel::RegionalFee,
                $pricing->regionFeeCents, [...$context, 'description' => 'Regional fee — '.$label],
            );
        }

        // --- Credits: where it goes -------------------------------------
        if ($asset->mainOwner !== null && $split['owner_cents'] > 0) {
            $this->ledger->post(
                $asset->mainOwner, LedgerDirection::Credit, LedgerLabel::AssetIncome,
                $split['owner_cents'], [...$context, 'description' => 'Income: '.$label],
            );
        }

        $llcCredit = $pricing->llcFeeCents + $split['llc_cents'];
        if ($llcCredit > 0) {
            $this->ledger->post(
                $asset->llc, LedgerDirection::Credit, LedgerLabel::LlcFee,
                $llcCredit, [...$context, 'description' => 'Fee: '.$label],
            );
        }

        $regionCredit = $pricing->regionFeeCents + $split['region_cents'];
        if ($regionCredit > 0) {
            $this->ledger->post(
                $asset->llc->region, LedgerDirection::Credit, LedgerLabel::RegionalFee,
                $regionCredit, [...$context, 'description' => 'Fee: '.$label],
            );
        }
    }

    /**
     * Live bookings whose occupied range overlaps the one given.
     *
     * Scoped to the same collection item where there is one — two units of a
     * collection do not contend with each other.
     *
     * @return Collection<int, Booking>
     */
    public function conflictsFor(
        Asset $asset,
        CarbonInterface $from,
        CarbonInterface $until,
        ?CollectionItem $item = null,
    ): Collection {
        return Booking::query()
            ->where('asset_id', $asset->getKey())
            ->when(
                $item !== null,
                fn ($q) => $q->where('collection_item_id', $item?->getKey()),
                fn ($q) => $q->whereNull('collection_item_id'),
            )
            ->live()
            ->overlapping($from, $until)
            ->lockForUpdate()
            ->get();
    }

    /**
     * How many bookings may hold the same slice at once.
     *
     * The asset itself is a single thing. A collection item stands for a
     * number of identical units, so it admits that many.
     */
    private function capacityOf(?CollectionItem $item): int
    {
        return $item === null ? 1 : max(1, $item->quantity);
    }

    /**
     * Every rule the prototype enforced only by disabling a button.
     */
    private function assertMayBook(User $user, Asset $asset): void
    {
        if ($user->isRcm()) {
            throw BookingNotPermitted::rcmMayNotBook();
        }

        if ($asset->isFrozenForRetirement()) {
            throw BookingNotPermitted::assetFrozenForRetirement();
        }

        // Derived from the ledger rather than trusting the cached flag.
        if ($this->ledger->hasOverdueCharges($user)) {
            throw BookingNotPermitted::billingSuspended();
        }

        if (! $this->hasAccessTo($user, $asset)) {
            throw BookingNotPermitted::notInPool();
        }
    }

    /**
     * An asset hat, any LLC role, or pool membership all grant access.
     */
    private function hasAccessTo(User $user, Asset $asset): bool
    {
        $assetHats = [HatType::AssetOwner, HatType::AssetManager, HatType::AssetAdmin];
        foreach ($assetHats as $hat) {
            if ($user->hasHat($hat, $asset)) {
                return true;
            }
        }

        $llcHats = [HatType::LlcOwner, HatType::LlcManager, HatType::LlcAdmin, HatType::LlcMember];
        foreach ($llcHats as $hat) {
            if ($user->hasHat($hat, $asset->llc)) {
                return true;
            }
        }

        return $user->pooledAssets()->whereKey($asset->getKey())->exists();
    }

    /**
     * Bumping deliberately breaks the no-overlap invariant for an instant.
     * Conflicts are displaced first, inside the same transaction, so the
     * invariant holds again at commit.
     *
     * @param  Collection<int, Booking>  $conflicts
     * @param  int  $capacity  how many may hold this slice at once
     * @return bool whether anything was displaced
     */
    private function resolveConflicts(
        Collection $conflicts,
        User $user,
        int $priority,
        bool $allowBump,
        int $capacity = 1,
    ): bool {
        // Overlapping is only a problem once the units run out. An item with
        // three of something admits three concurrent bookings; the fourth is
        // what contends.
        if ($conflicts->count() < $capacity) {
            return false;
        }

        if (! $allowBump) {
            throw BookingNotPermitted::slotTaken();
        }

        // Free only as many units as it takes to fit — displacing three
        // members to take one slot of a three-unit item would be absurd.
        $toFree = $conflicts->count() - $capacity + 1;

        $displaceable = $conflicts
            ->sortBy('priority')
            ->take($toFree);

        foreach ($displaceable as $conflict) {
            if ($conflict->priority >= $priority) {
                throw BookingNotPermitted::mayNotBump();
            }
        }

        foreach ($displaceable as $conflict) {
            $conflict->update([
                'status' => BookingStatus::Bumped,
                'bumped_by_user_id' => $user->id,
                'bumped_at' => now(),
            ]);
        }

        return true;
    }

    private function initialStatus(User $user, Asset $asset, bool $requiresApproval): BookingStatus
    {
        if (! $requiresApproval) {
            return BookingStatus::Confirmed;
        }

        // Someone who manages the asset does not wait on their own approval.
        $manages = $user->hasHat(HatType::AssetOwner, $asset)
            || $user->hasHat(HatType::AssetManager, $asset);

        return $manages ? BookingStatus::Confirmed : BookingStatus::Pending;
    }

    private function mesosBetween(CarbonInterface $startsAt, CarbonInterface $endsAt): int
    {
        $minutes = $startsAt->diffInMinutes($endsAt);

        return (int) round($minutes / (int) config('tribeshare.bookings.meso_minutes'));
    }
}
