<?php

namespace App\Services\Requests;

use App\Enums\HatType;
use App\Enums\NotificationKind;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Asset;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\MemberRequest;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Permissions\HatService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The generic approval queue.
 *
 * **One resolution path.** In the prototype, resolving requests in a batch
 * handled both outcomes for asset submissions — approval cleared the asset's
 * pending flag and activated its hats, denial recycled it and stripped them —
 * while resolving a single request handled only denial. Approving one
 * on its own updated the request's status and did nothing else, so an asset
 * approved one at a time was never actually approved.
 *
 * Here `approve()` and `deny()` do the whole job, and the batch methods are
 * loops over them. The two paths cannot drift because there is only one.
 */
class RequestService
{
    public function __construct(
        private readonly HatService $hats,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Raise a request.
     *
     * When it implies a role, the hat is created immediately but **inactive
     * and pending**. Approval activates it; denial deletes it. The intended
     * end state exists from the start, so approving is a state change rather
     * than a creation — a neat mechanism, and kept.
     *
     * @param  array<string, mixed>  $payload
     */
    public function raise(
        User $requester,
        RequestType $type,
        ?Model $target = null,
        ?string $message = null,
        array $payload = [],
        ?HatType $hatType = null,
    ): MemberRequest {
        return DB::transaction(function () use ($requester, $type, $target, $message, $payload, $hatType): MemberRequest {
            $existing = MemberRequest::query()
                ->pending()
                ->where('requested_by', $requester->id)
                ->where('type', $type)
                ->when($target !== null, fn ($q) => $q
                    ->where('target_type', $target->getMorphClass())
                    ->where('target_id', $target->getKey()))
                ->first();

            if ($existing !== null) {
                throw new RuntimeException('You already have that request outstanding.');
            }

            $pendingHat = $type->impliesHat()
                ? $this->createPendingHat($requester, $type, $target, $hatType)
                : null;

            $request = MemberRequest::create([
                'type' => $type,
                'status' => RequestStatus::Pending,
                'requested_by' => $requester->id,
                'target_type' => $target?->getMorphClass(),
                'target_id' => $target?->getKey(),
                'pending_hat_id' => $pendingHat?->id,
                'message' => $message,
                // The INTENT only. A cap-override request stores what the
                // member wants to book, never a fully-formed booking with
                // its ledger entries — the prototype replayed those verbatim
                // on approval, which computed money on the client and froze
                // the price at request time.
                'payload' => $payload,
            ]);

            $this->notifyApprovers($request);

            return $request;
        });
    }

    /**
     * Approve one request, doing everything approval means.
     */
    public function approve(MemberRequest $request, User $resolver, ?string $note = null): MemberRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $resolver, $note): MemberRequest {
            // Activating through HatService, so a grant made by approval is
            // subject to the same rules as any other grant.
            if ($request->pendingHat !== null) {
                $this->hats->activate($request->pendingHat);
            }

            if ($request->type === RequestType::AddAsset && $request->target instanceof Asset) {
                // The step the single-resolution path skipped entirely.
                $request->target->forceFill([
                    'approved_at' => now(),
                    'recycled_at' => null,
                ])->save();
            }

            $request->update([
                'status' => RequestStatus::Approved,
                'resolved_by' => $resolver->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            $this->notifyRequester($request, 'approved', $note);

            return $request->refresh();
        });
    }

    /**
     * Deny one request, doing everything denial means.
     */
    public function deny(MemberRequest $request, User $resolver, ?string $note = null): MemberRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $resolver, $note): MemberRequest {
            // A hat that exists only in anticipation of approval goes when
            // approval does not come. Deleted directly rather than through
            // HatService: an inactive hat never conferred anything, so the
            // revocation guards have nothing to protect.
            $request->pendingHat?->delete();

            if ($request->type === RequestType::AddAsset && $request->target instanceof Asset) {
                $request->target->forceFill(['recycled_at' => now()])->save();
            }

            $request->update([
                'status' => RequestStatus::Denied,
                'resolved_by' => $resolver->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            $this->notifyRequester($request, 'declined', $note);

            return $request->refresh();
        });
    }

    /**
     * Withdraw a request you raised.
     *
     * An asset submission returns to draft and loses its pending hats — the
     * member gets their work back rather than losing it.
     */
    public function withdraw(MemberRequest $request, User $actor): MemberRequest
    {
        if ($request->requested_by !== $actor->id) {
            throw new RuntimeException('Only the member who raised a request may withdraw it.');
        }

        $this->assertPending($request);

        return DB::transaction(function () use ($request): MemberRequest {
            $request->pendingHat?->delete();

            if ($request->type === RequestType::AddAsset && $request->target instanceof Asset) {
                $request->target->forceFill(['approved_at' => null])->save();
            }

            $request->update([
                'status' => RequestStatus::Withdrawn,
                'resolved_at' => now(),
            ]);

            return $request->refresh();
        });
    }

    /**
     * Approve several. A loop over the single path, never a second one.
     *
     * @param  iterable<int, MemberRequest>  $requests
     * @return array<int, MemberRequest>
     */
    public function approveMany(iterable $requests, User $resolver, ?string $note = null): array
    {
        $resolved = [];

        foreach ($requests as $request) {
            $resolved[] = $this->approve($request, $resolver, $note);
        }

        return $resolved;
    }

    /**
     * @param  iterable<int, MemberRequest>  $requests
     * @return array<int, MemberRequest>
     */
    public function denyMany(iterable $requests, User $resolver, ?string $note = null): array
    {
        $resolved = [];

        foreach ($requests as $request) {
            $resolved[] = $this->deny($request, $resolver, $note);
        }

        return $resolved;
    }

    // --- Internals ---------------------------------------------------------

    private function assertPending(MemberRequest $request): void
    {
        if (! $request->status->isPending()) {
            throw new RuntimeException('That request has already been resolved.');
        }
    }

    /**
     * The hat a request anticipates: real, but inert until approval.
     */
    private function createPendingHat(User $requester, RequestType $type, ?Model $target, ?HatType $hatType): ?Hat
    {
        $resolved = $hatType ?? match ($type) {
            RequestType::JoinPool, RequestType::AddAsset => HatType::AssetPoolMember,
            RequestType::JoinLlc => HatType::LlcMember,
            default => null,
        };

        if ($resolved === null || $target === null) {
            return null;
        }

        // An asset submission makes the member its owner, not merely a member
        // of its pool.
        if ($type === RequestType::AddAsset) {
            $resolved = HatType::AssetOwner;
        }

        if ($this->hats->holds($requester, $resolved, $target)) {
            throw new RuntimeException('You already hold that role.');
        }

        return Hat::create([
            'user_id' => $requester->id,
            'type' => $resolved,
            'scopeable_type' => $target->getMorphClass(),
            'scopeable_id' => $target->getKey(),
            'active' => false,
        ]);
    }

    private function notifyApprovers(MemberRequest $request): void
    {
        foreach ($this->approversFor($request) as $approver) {
            $this->notifications->send(
                $approver,
                NotificationKind::Request,
                $request->type->label(),
                "{$request->requester->name} is waiting on a decision.",
                subject: $request,
            );
        }
    }

    private function notifyRequester(MemberRequest $request, string $outcome, ?string $note): void
    {
        $this->notifications->send(
            $request->requester,
            NotificationKind::Request,
            "{$request->type->label()} {$outcome}",
            $note,
            subject: $request,
        );
    }

    /**
     * Who should be told a request is waiting.
     *
     * @return Collection<int, User>
     */
    private function approversFor(MemberRequest $request)
    {
        $target = $request->target;

        $llc = match (true) {
            $target instanceof Llc => $target,
            $target instanceof Asset => $target->llc,
            default => null,
        };

        if ($llc === null) {
            return User::query()->holdingHat(HatType::Rcm)->get();
        }

        return User::query()
            ->holdingHat([HatType::LlcOwner, HatType::LlcManager, HatType::LlcAdmin], $llc)
            ->get();
    }
}
