<?php

namespace App\Services\Ledger;

use App\Enums\ChargeStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use App\Models\LedgerEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The money model: a running tally, not invoices.
 *
 * Every figure here is DERIVED from ledger entries plus confirmed payments.
 * Nothing is cached or stored — `users.billing_suspended` is the sole
 * exception, and it is rebuilt from these methods rather than trusted.
 */
class LedgerService
{
    /**
     * Post an entry. The only way money should ever enter the ledger.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function post(
        Model $owner,
        LedgerDirection $direction,
        LedgerLabel $label,
        int $amountCents,
        array $attributes = [],
    ): LedgerEntry {
        if ($amountCents < 0) {
            throw new InvalidArgumentException(
                'Ledger amounts are always positive; the direction carries the sign.'
            );
        }

        return LedgerEntry::create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'direction' => $direction,
            'label' => $label,
            'amount_cents' => $amountCents,
            ...$attributes,
        ]);
    }

    /**
     * Correct an earlier entry by posting its mirror image.
     *
     * History is never rewritten — this is the only sanctioned correction.
     */
    public function reverse(LedgerEntry $entry, string $reason, ?User $by = null): LedgerEntry
    {
        return LedgerEntry::create([
            'owner_type' => $entry->owner_type,
            'owner_id' => $entry->owner_id,
            'direction' => $entry->direction->opposite(),
            'label' => LedgerLabel::Reversal,
            'amount_cents' => $entry->amount_cents,
            'description' => 'Reversal: '.$entry->description,
            'booking_id' => $entry->booking_id,
            'asset_id' => $entry->asset_id,
            'reverses_id' => $entry->id,
            'reason' => $reason,
            'created_by' => $by?->id,
        ]);
    }

    /**
     * What a member owes: charges, less income, less confirmed payments.
     *
     * Positive means they owe; negative means they hold credit.
     */
    public function netBalanceCents(User $user): int
    {
        return $this->totalChargesCents($user)
            - $this->totalIncomeCents($user)
            - $this->confirmedPaymentsCents($user);
    }

    /**
     * Overpayment held on account, floored at zero.
     */
    public function creditBalanceCents(User $user): int
    {
        return max(0, -$this->netBalanceCents($user));
    }

    public function totalChargesCents(User $user): int
    {
        return (int) $user->ledgerEntries()->debits()->sum('amount_cents');
    }

    public function totalIncomeCents(User $user): int
    {
        return (int) $user->ledgerEntries()->credits()->sum('amount_cents');
    }

    public function confirmedPaymentsCents(User $user): int
    {
        return (int) $user->payments()->confirmed()->sum('amount_cents');
    }

    /**
     * Credits and debits for an LLC or region, which use a plain rollup
     * rather than the member's charges-versus-payments model.
     *
     * @return array{credits_cents: int, debits_cents: int, net_cents: int}
     */
    public function entityBalance(Model $owner): array
    {
        $credits = (int) LedgerEntry::query()->ownedBy($owner)->credits()->sum('amount_cents');
        $debits = (int) LedgerEntry::query()->ownedBy($owner)->debits()->sum('amount_cents');

        return [
            'credits_cents' => $credits,
            'debits_cents' => $debits,
            'net_cents' => $credits - $debits,
        ];
    }

    /**
     * Every charge, annotated with how much of it is settled and its age.
     *
     * Income and confirmed payments are allocated to charges OLDEST FIRST,
     * so a member paying part of what they owe clears their oldest debts.
     *
     * @return array<int, ChargeAllocation>
     */
    public function chargeAllocations(User $user, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        $available = $this->totalIncomeCents($user) + $this->confirmedPaymentsCents($user);

        return $this->charges($user)
            ->map(function (LedgerEntry $charge) use (&$available, $now): ChargeAllocation {
                $paid = min($available, $charge->amount_cents);
                $available -= $paid;
                $remaining = $charge->amount_cents - $paid;

                return new ChargeAllocation(
                    entry: $charge,
                    paidCents: $paid,
                    remainingCents: $remaining,
                    status: $this->statusFor($charge, $remaining, $paid, $now),
                );
            })
            ->all();
    }

    /**
     * An overdue charge suspends the member from booking.
     */
    public function hasOverdueCharges(User $user, ?CarbonInterface $now = null): bool
    {
        foreach ($this->chargeAllocations($user, $now) as $allocation) {
            if ($allocation->status->suspendsBooking()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a new charge would push the member past their balance cap.
     */
    public function wouldExceedCap(User $user, int $newChargeCents): bool
    {
        return $this->netBalanceCents($user) + $newChargeCents > $user->carried_balance_limit_cents;
    }

    /**
     * Credit only becomes payable once the income that created it has
     * matured. Anything younger is reported with the date it unlocks.
     */
    public function creditAvailability(User $user, ?CarbonInterface $now = null): CreditAvailability
    {
        $now ??= now();
        $credit = $this->creditBalanceCents($user);

        if ($credit <= 0) {
            return new CreditAvailability(0, 0, []);
        }

        $maturityDays = (int) config('tribeshare.billing.payout_maturity_days');
        $remaining = $credit;
        $available = 0;
        $upcoming = [];

        // Credit is attributed to the oldest income first, matching the FIFO
        // allocation used everywhere else.
        foreach ($this->income($user) as $entry) {
            if ($remaining <= 0) {
                break;
            }

            $portion = min($remaining, $entry->amount_cents);
            $remaining -= $portion;

            $unlocksAt = $entry->created_at->copy()->addDays($maturityDays);

            if ($unlocksAt->lessThanOrEqualTo($now)) {
                $available += $portion;

                continue;
            }

            $key = $unlocksAt->toDateString();
            $upcoming[$key] = ($upcoming[$key] ?? 0) + $portion;
        }

        ksort($upcoming);

        return new CreditAvailability(
            totalCents: $credit,
            availableCents: $available,
            upcoming: array_map(
                fn (string $date, int $cents) => ['date' => Carbon::parse($date), 'amount_cents' => $cents],
                array_keys($upcoming),
                array_values($upcoming),
            ),
        );
    }

    public function canRequestPayout(User $user, ?CarbonInterface $now = null): bool
    {
        return $this->creditAvailability($user, $now)->availableCents > 0
            && ! $user->payoutRequests()->pending()->exists();
    }

    /**
     * @return Collection<int, LedgerEntry>
     */
    private function charges(User $user): Collection
    {
        return $user->ledgerEntries()->debits()->orderBy('created_at')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, LedgerEntry>
     */
    private function income(User $user): Collection
    {
        return $user->ledgerEntries()->credits()->orderBy('created_at')->orderBy('id')->get();
    }

    private function statusFor(LedgerEntry $charge, int $remaining, int $paid, CarbonInterface $now): ChargeStatus
    {
        if ($remaining === 0) {
            return ChargeStatus::Paid;
        }

        $dueAt = $charge->due_at
            ?? $charge->created_at->copy()->addDays((int) config('tribeshare.billing.due_days'));

        $overdueAt = $dueAt->copy()->addDays((int) config('tribeshare.billing.grace_days'));

        if ($now->greaterThan($overdueAt)) {
            return ChargeStatus::Overdue;
        }

        if ($now->greaterThan($dueAt)) {
            return ChargeStatus::Due;
        }

        return $paid > 0 ? ChargeStatus::Partial : ChargeStatus::Pending;
    }
}
