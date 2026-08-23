<?php

namespace App\Services\Organisation;

use App\Enums\HatType;
use App\Enums\NotificationKind;
use App\Enums\OffboardingStatus;
use App\Exceptions\HatChangeRefused;
use App\Models\Asset;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\LlcLeaveQueue;
use App\Models\MemberRemovalQueue;
use App\Models\Notification;
use App\Models\Region;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Permissions\HatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Leaving, in the two ways a member can.
 *
 * Neither removal is immediate. Both queue, wait for obligations to settle,
 * and then fire — so a member cannot be removed out from under a booking, and
 * cannot walk away from a balance.
 *
 * The prototype held both as flags and an array on the member
 * (`pendingRecycle`, `quitQueue`), which could not record who queued them,
 * when, or why the queue eventually fired.
 */
class OffboardingService
{
    public function __construct(
        private readonly ObligationService $obligations,
        private readonly LifecycleService $lifecycle,
        private readonly HatService $hats,
        private readonly NotificationService $notifications,
    ) {}

    // --- Removal, by someone else ------------------------------------------

    public function queueForRemoval(User $user, ?User $by = null, ?string $reason = null): MemberRemovalQueue
    {
        $existing = MemberRemovalQueue::query()->pending()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $queue = MemberRemovalQueue::create([
            'user_id' => $user->id,
            'status' => OffboardingStatus::Queued,
            'reason' => $reason,
            'queued_by' => $by?->id,
            'queued_at' => now(),
        ]);

        $this->notifications->send(
            $user,
            NotificationKind::Recycled,
            'Your membership is being closed',
            $this->outstandingSummary($this->obligations->forMember($user)),
            subject: $queue,
        );

        return $queue;
    }

    public function cancelRemoval(MemberRemovalQueue $queue, ?User $by = null): MemberRemovalQueue
    {
        if (! $queue->status->isPending()) {
            throw new RuntimeException('That removal is no longer queued.');
        }

        $queue->update([
            'status' => OffboardingStatus::Cancelled,
            'cancelled_by' => $by?->id,
            'cancelled_at' => now(),
        ]);

        return $queue->refresh();
    }

    // --- Leaving, by the member themselves ---------------------------------

    /**
     * A member's own request to leave an LLC.
     *
     * Self-service, but not instant.
     */
    public function queueLeave(User $user, Llc $llc): LlcLeaveQueue
    {
        if (! $this->hats->holds($user, HatType::LlcMember, $llc)) {
            throw new RuntimeException('You are not a member of that LLC.');
        }

        $existing = LlcLeaveQueue::query()
            ->pending()
            ->where('user_id', $user->id)
            ->where('llc_id', $llc->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return LlcLeaveQueue::create([
            'user_id' => $user->id,
            'llc_id' => $llc->id,
            'status' => OffboardingStatus::Queued,
            'queued_at' => now(),
        ]);
    }

    /**
     * Change your mind, while it is still queued.
     */
    public function cancelLeave(LlcLeaveQueue $queue): LlcLeaveQueue
    {
        if (! $queue->status->isPending()) {
            throw new RuntimeException('That departure is no longer queued.');
        }

        $queue->update([
            'status' => OffboardingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return $queue->refresh();
    }

    // --- Firing ------------------------------------------------------------

    /**
     * Fire everything that has come clear.
     *
     * Idempotent, and called both on the timed sweep and after any booking
     * status change — so settling the last booking fires the queue at once
     * rather than waiting for the next tick.
     *
     * @return array{removed: int, left: int, recycled: int}
     */
    public function sweep(): array
    {
        return [
            'removed' => $this->fireRemovals(),
            'left' => $this->fireDepartures(),
            'recycled' => $this->fireRetirements(),
        ];
    }

    private function fireRemovals(): int
    {
        $fired = 0;

        foreach (MemberRemovalQueue::query()->pending()->with('user')->get() as $queue) {
            if ($queue->user === null || ! $this->obligations->memberIsClear($queue->user)) {
                continue;
            }

            DB::transaction(function () use ($queue): void {
                // Every hat goes, so the member belongs nowhere. Deleted
                // directly rather than through HatService: the guards exist
                // to stop a member being stranded without a membership, and
                // that is precisely what removal is.
                Hat::query()->where('user_id', $queue->user->id)->delete();

                $queue->update([
                    'status' => OffboardingStatus::Fired,
                    'fired_at' => now(),
                ]);

                $queue->user->forceFill(['recycled_at' => now()])->save();
            });

            $fired++;
        }

        return $fired;
    }

    private function fireDepartures(): int
    {
        $fired = 0;

        foreach (LlcLeaveQueue::query()->pending()->with(['user', 'llc'])->get() as $queue) {
            if ($queue->user === null || $queue->llc === null) {
                continue;
            }

            if (! $this->obligations->memberIsClearOf($queue->user, $queue->llc)) {
                continue;
            }

            try {
                DB::transaction(function () use ($queue): void {
                    // Through the guarded service: leaving your last LLC
                    // would strip your last membership, and that refusal
                    // holds here as it holds everywhere.
                    $hats = Hat::query()
                        ->where('user_id', $queue->user->id)
                        ->where('scopeable_type', $queue->llc->getMorphClass())
                        ->where('scopeable_id', $queue->llc->id)
                        ->get();

                    foreach ($hats as $hat) {
                        $this->hats->revoke($hat);
                    }

                    $queue->update([
                        'status' => OffboardingStatus::Fired,
                        'fired_at' => now(),
                    ]);
                });
            } catch (HatChangeRefused $e) {
                // A guard refused this one. The sweep is a scheduled job over
                // every queue there is, so one departure that cannot complete
                // must not take the rest of them down with it: it stays
                // queued, the member is told why once, and everything else
                // carries on.
                $this->explainRefusal($queue, $e);

                continue;
            }

            $fired++;
        }

        return $fired;
    }

    /**
     * Retire the entities whose obligations have settled.
     */
    private function fireRetirements(): int
    {
        $recycled = 0;

        foreach ([Region::class, Llc::class, Asset::class] as $model) {
            $queued = $model::query()
                ->whereNotNull('queued_for_retirement_at')
                ->whereNull('recycled_at')
                ->get();

            foreach ($queued as $entity) {
                try {
                    if ($this->lifecycle->recycle($entity)) {
                        $recycled++;
                    }
                } catch (RuntimeException $e) {
                    Log::warning("Retirement held on {$model} {$entity->getKey()}: {$e->getMessage()}");
                }
            }
        }

        return $recycled;
    }

    /**
     * Tell a member their departure is stuck, once.
     *
     * Repeating it every hour would be its own kind of failure.
     */
    private function explainRefusal(LlcLeaveQueue $queue, HatChangeRefused $e): void
    {
        $already = Notification::query()
            ->where('user_id', $queue->user_id)
            ->where('subject_type', $queue->getMorphClass())
            ->where('subject_id', $queue->id)
            ->exists();

        if ($already) {
            return;
        }

        $this->notifications->send(
            $queue->user,
            NotificationKind::System,
            'Your departure is on hold',
            $e->getMessage(),
            subject: $queue,
        );
    }

    /**
     * @param  array<int, Obligation>  $obligations
     */
    private function outstandingSummary(array $obligations): string
    {
        if ($obligations === []) {
            return 'Nothing is outstanding, so this will complete shortly.';
        }

        return 'It completes once these are settled: '.implode(' ', array_map(
            fn (Obligation $obligation) => $obligation->summary,
            $obligations,
        ));
    }
}
