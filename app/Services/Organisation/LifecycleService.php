<?php

namespace App\Services\Organisation;

use App\Contracts\Retirable;
use App\Enums\HatType;
use App\Enums\NotificationKind;
use App\Enums\QueueSource;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\Region;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Retiring, restoring, suspending and deleting the ownership hierarchy.
 *
 * Two things this does not reproduce.
 *
 * **No toggles.** The prototype's LLC suspension was one action that flipped
 * the current state, so a caller had to know the state to predict the
 * outcome. Suspending suspends; unsuspending unsuspends. The same applies to
 * conversation archiving and calendar publishing elsewhere.
 *
 * **Deletion tests the ledger.** The prototype's gate waited on invoices,
 * which stopped existing when billing moved to a running tally — so the money
 * half of the check had been dead for some time.
 */
class LifecycleService
{
    public function __construct(
        private readonly ObligationService $obligations,
        private readonly NotificationService $notifications,
    ) {}

    // --- Retirement --------------------------------------------------------

    /**
     * Queue a region for retirement, cascading down.
     *
     * Nothing is deleted. The queue freezes booking everywhere beneath it and
     * fires automatically once obligations settle.
     */
    public function retireRegion(Region $region, ?User $by = null): Region
    {
        return DB::transaction(function () use ($region, $by): Region {
            $this->stampQueued($region, QueueSource::Direct, $by);

            $llcs = $region->llcs()->whereNull('recycled_at')->whereNull('queued_for_retirement_at')->get();

            foreach ($llcs as $llc) {
                $this->stampQueued($llc, QueueSource::Region, $by);

                foreach ($llc->assets()->whereNull('recycled_at')->whereNull('queued_for_retirement_at')->get() as $asset) {
                    $this->stampQueued($asset, QueueSource::Llc, $by);
                }
            }

            // LLC owners are the right audience: they are the people who must
            // settle the obligations before any of this fires.
            $this->notifyOwners($llcs, $region);

            return $region->refresh();
        });
    }

    public function retireLlc(Llc $llc, ?User $by = null): Llc
    {
        return DB::transaction(function () use ($llc, $by): Llc {
            $this->stampQueued($llc, QueueSource::Direct, $by);

            foreach ($llc->assets()->whereNull('recycled_at')->whereNull('queued_for_retirement_at')->get() as $asset) {
                $this->stampQueued($asset, QueueSource::Llc, $by);
            }

            return $llc->refresh();
        });
    }

    /**
     * Unwind a retirement — and only what that retirement caused.
     *
     * An LLC queued in its own right stays queued, and one separately marked
     * for deletion is left alone: a restore must not resurrect something
     * condemned on its own account.
     */
    public function restoreRegion(Region $region): Region
    {
        return DB::transaction(function () use ($region): Region {
            $llcs = $region->llcs()
                ->whereNull('marked_for_deletion_at')
                ->where(fn ($q) => $q->where('queued_source', QueueSource::Region)
                    ->orWhere('recycled_source', QueueSource::Region))
                ->get();

            foreach ($llcs as $llc) {
                $assets = $llc->assets()
                    ->whereNull('marked_for_deletion_at')
                    ->where(fn ($q) => $q->where('queued_source', QueueSource::Llc)
                        ->orWhere('recycled_source', QueueSource::Llc))
                    ->get();

                foreach ($assets as $asset) {
                    $this->clearQueued($asset);
                }

                $this->clearQueued($llc);
            }

            $this->clearQueued($region);

            return $region->refresh();
        });
    }

    public function restoreLlc(Llc $llc): Llc
    {
        return DB::transaction(function () use ($llc): Llc {
            $assets = $llc->assets()
                ->whereNull('marked_for_deletion_at')
                ->where(fn ($q) => $q->where('queued_source', QueueSource::Llc)
                    ->orWhere('recycled_source', QueueSource::Llc))
                ->get();

            foreach ($assets as $asset) {
                $this->clearQueued($asset);
            }

            $this->clearQueued($llc);

            return $llc->refresh();
        });
    }

    /**
     * Actually retire what was queued, once nothing is outstanding.
     *
     * Called by the sweep, and after any booking status change, so settling
     * the last booking fires the queue immediately rather than waiting a tick.
     */
    public function recycle(Model&Retirable $entity): bool
    {
        if (! $entity->isQueuedForRetirement() || $entity->isRecycled()) {
            return false;
        }

        if (! $this->obligations->entityIsClear($entity)) {
            return false;
        }

        $entity->forceFill([
            'recycled_at' => now(),
            'recycled_source' => $entity->queuedSource(),
        ])->save();

        return true;
    }

    // --- Suspension: explicit verbs, never a toggle ------------------------

    public function suspend(Model&Retirable $entity, ?User $by = null): void
    {
        if ($entity->isSuspended()) {
            return;
        }

        $entity->forceFill(['suspended_at' => now()])->save();

        if ($entity instanceof Llc) {
            $this->notifyMembers($entity, 'This LLC has been suspended.', NotificationKind::Suspended);
        }
    }

    /**
     * Lift a suspension, and nothing else.
     *
     * The prototype's restore cleared recycled *and* suspended together, so
     * it was not the inverse of either action alone.
     */
    public function unsuspend(Model&Retirable $entity): void
    {
        if (! $entity->isSuspended()) {
            return;
        }

        $entity->forceFill(['suspended_at' => null])->save();

        if ($entity instanceof Llc) {
            // Members were told on suspension and not on release, which left
            // them believing it was still in force.
            $this->notifyMembers($entity, 'This LLC has been reinstated.', NotificationKind::System);
        }
    }

    // --- Deletion ----------------------------------------------------------

    /**
     * Record the intent to delete, and suspend meanwhile.
     */
    public function markForDeletion(Model&Retirable $entity, ?User $by = null): void
    {
        $entity->forceFill([
            'marked_for_deletion_at' => now(),
            'marked_for_deletion_by' => $by?->id,
        ])->save();

        $this->suspend($entity, $by);
    }

    public function unmarkForDeletion(Model&Retirable $entity): void
    {
        $entity->forceFill([
            'marked_for_deletion_at' => null,
            'marked_for_deletion_by' => null,
        ])->save();

        $this->unsuspend($entity);
    }

    /**
     * Delete, if it is safe to.
     *
     * @throws RuntimeException when something is still outstanding
     */
    public function delete(Model&Retirable $entity): void
    {
        $blockers = $this->deletionBlockers($entity);

        if ($blockers !== []) {
            throw new RuntimeException('This cannot be deleted yet: '.implode(' ', $blockers));
        }

        $this->performDelete($entity);
    }

    /**
     * Delete over the structural objections — but never over money.
     *
     * **Decided: force-delete survives, and may not ignore the ledger.** It
     * skips the tidiness checks an RCM can reasonably override, such as LLCs
     * that have not been wound up first. An unsettled ledger still refuses
     * it, on the same principle that governance cannot override a guard: the
     * money has to belong to someone.
     *
     * The prototype's force-delete skipped every check, and the check it
     * skipped was already blind to money owed.
     *
     * @throws RuntimeException when money is unsettled
     */
    public function forceDelete(Model&Retirable $entity): void
    {
        $monetary = $this->monetaryBlockers($entity);

        if ($monetary !== []) {
            throw new RuntimeException(
                'Even a forced deletion may not discard money: '.implode(' ', $monetary)
            );
        }

        $this->performDelete($entity);
    }

    /**
     * Everything refusing an ordinary deletion.
     *
     * @return array<int, string>
     */
    public function deletionBlockers(Model&Retirable $entity): array
    {
        $blockers = $this->monetaryBlockers($entity);

        if ($entity instanceof Region) {
            $remaining = $entity->llcs()->whereNull('recycled_at')->count();

            if ($remaining > 0) {
                $blockers[] = "{$remaining} LLC(s) still belong to it.";
            }
        }

        if ($entity instanceof Llc) {
            $remaining = $entity->assets()->whereNull('recycled_at')->count();

            if ($remaining > 0) {
                $blockers[] = "{$remaining} asset(s) still belong to it.";
            }
        }

        return $blockers;
    }

    /**
     * The subset no deletion may ever override.
     *
     * @return array<int, string>
     */
    public function monetaryBlockers(Model&Retirable $entity): array
    {
        return array_map(
            fn (Obligation $obligation) => $obligation->summary,
            $this->obligations->forEntity($entity),
        );
    }

    // --- Internals ---------------------------------------------------------

    private function performDelete(Model $entity): void
    {
        DB::transaction(function () use ($entity): void {
            // On a region's deletion every Regional Member hat scoped to it
            // is stripped — those members no longer belong anywhere by way
            // of this region.
            Hat::query()
                ->where('scopeable_type', $entity->getMorphClass())
                ->where('scopeable_id', $entity->getKey())
                ->delete();

            $entity->delete();
        });
    }

    private function stampQueued(Model&Retirable $entity, QueueSource $source, ?User $by): void
    {
        $entity->forceFill([
            'queued_for_retirement_at' => now(),
            'queued_source' => $source,
            'queued_by' => $by?->id,
        ])->save();
    }

    private function clearQueued(Model&Retirable $entity): void
    {
        $entity->forceFill([
            'queued_for_retirement_at' => null,
            'queued_source' => null,
            'queued_by' => null,
            'recycled_at' => null,
            'recycled_source' => null,
        ])->save();
    }

    /**
     * @param  iterable<int, Llc>  $llcs
     */
    private function notifyOwners(iterable $llcs, Region $region): void
    {
        foreach ($llcs as $llc) {
            $owners = User::query()->holdingHat(HatType::LlcOwner, $llc)->get();

            foreach ($owners as $owner) {
                $this->notifications->send(
                    $owner,
                    NotificationKind::System,
                    "{$region->name} is being retired",
                    "Settle open bookings and balances on {$llc->name} to complete it.",
                    subject: $llc,
                );
            }
        }
    }

    private function notifyMembers(Llc $llc, string $body, NotificationKind $kind): void
    {
        $members = User::query()->holdingHat(scope: $llc)->get();

        foreach ($members as $member) {
            $this->notifications->send($member, $kind, $llc->name, $body, subject: $llc);
        }
    }
}
