<?php

namespace App\Services\Permissions;

use App\Models\Llc;
use App\Models\Suspension;
use App\Models\User;
use App\Services\Ledger\LedgerService;

/**
 * Suspension — an independent gate that cuts across hats.
 *
 * Three unrelated concepts, deliberately kept apart:
 *
 *  1. global      — issued by an RCM, bars everything
 *  2. scoped      — issued per LLC, bars that LLC
 *  3. billing     — DERIVED from the ledger, bars booking only
 *
 * The first two are entries here. The third is never stored as truth: it is
 * computed from what the member owes.
 */
class SuspensionService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function suspendGlobally(User $user, ?User $by = null, ?string $note = null): Suspension
    {
        return Suspension::create([
            'user_id' => $user->id,
            'suspended_by' => $by?->id,
            'note' => $note,
            'suspended_at' => now(),
        ]);
    }

    public function suspendFrom(User $user, Llc $llc, ?User $by = null, ?string $note = null): Suspension
    {
        return Suspension::create([
            'user_id' => $user->id,
            'scopeable_type' => $llc->getMorphClass(),
            'scopeable_id' => $llc->id,
            'suspended_by' => $by?->id,
            'note' => $note,
            'suspended_at' => now(),
        ]);
    }

    /**
     * Lifting records who and when rather than deleting, so a member's
     * history survives.
     */
    public function lift(Suspension $suspension, ?User $by = null): void
    {
        $suspension->update([
            'lifted_at' => now(),
            'lifted_by' => $by?->id,
        ]);
    }

    public function isSuspendedGlobally(User $user): bool
    {
        return $user->suspensions()->active()->global()->exists();
    }

    /**
     * A global suspension covers every LLC as well as its own scope.
     */
    public function isSuspendedFrom(User $user, Llc $llc): bool
    {
        if ($this->isSuspendedGlobally($user)) {
            return true;
        }

        return $user->suspensions()
            ->active()
            ->where('scopeable_type', $llc->getMorphClass())
            ->where('scopeable_id', $llc->id)
            ->exists();
    }

    public function isSuspendedAnywhere(User $user): bool
    {
        return $user->suspensions()->active()->exists();
    }

    /**
     * Derived from the ledger, never read from a flag.
     */
    public function isBillingSuspended(User $user): bool
    {
        return $this->ledger->hasOverdueCharges($user);
    }

    /**
     * Rebuild the cached flag from what the ledger actually says.
     *
     * The cache exists for query speed; this is what makes it honest, and
     * the scheduled sweep calls it.
     */
    public function refreshBillingSuspension(User $user): bool
    {
        $suspended = $this->isBillingSuspended($user);

        if ($user->billing_suspended !== $suspended) {
            $user->forceFill(['billing_suspended' => $suspended])->save();
        }

        return $suspended;
    }
}
