<?php

use App\Enums\ChargeStatus;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\Ledger\LedgerService;

beforeEach(function () {
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();
});

it('reports a zero balance for a member with no history', function () {
    expect($this->ledger->netBalanceCents($this->user))->toBe(0)
        ->and($this->ledger->creditBalanceCents($this->user))->toBe(0);
});

it('treats a positive net balance as money owed', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(120_00)->create();

    expect($this->ledger->netBalanceCents($this->user))->toBe(120_00)
        ->and($this->ledger->creditBalanceCents($this->user))->toBe(0);
});

it('counts confirmed payments but ignores pending ones', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(100_00)->create();

    Payment::factory()->for($this->user)->of(40_00)->create();
    expect($this->ledger->netBalanceCents($this->user))->toBe(100_00);

    Payment::factory()->for($this->user)->of(40_00)->confirmed()->create();
    expect($this->ledger->netBalanceCents($this->user))->toBe(60_00);
});

it('treats overpayment as credit rather than negative debt', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(30_00)->create();
    Payment::factory()->for($this->user)->of(50_00)->confirmed()->create();

    expect($this->ledger->netBalanceCents($this->user))->toBe(-20_00)
        ->and($this->ledger->creditBalanceCents($this->user))->toBe(20_00);
});

it('allocates payments to the oldest charge first', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(50_00)->agedDays(30)
        ->create(['description' => 'oldest']);
    LedgerEntry::factory()->ownedBy($this->user)->charge(50_00)->agedDays(2)
        ->create(['description' => 'newest']);

    // Enough to clear the first charge and no more.
    Payment::factory()->for($this->user)->of(50_00)->confirmed()->create();

    $allocations = $this->ledger->chargeAllocations($this->user);

    expect($allocations[0]->entry->description)->toBe('oldest')
        ->and($allocations[0]->status)->toBe(ChargeStatus::Paid)
        ->and($allocations[0]->remainingCents)->toBe(0)
        ->and($allocations[1]->entry->description)->toBe('newest')
        ->and($allocations[1]->remainingCents)->toBe(50_00);
});

it('marks a part-paid charge as partial while it is still young', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(100_00)->create();
    Payment::factory()->for($this->user)->of(30_00)->confirmed()->create();

    $allocation = $this->ledger->chargeAllocations($this->user)[0];

    expect($allocation->status)->toBe(ChargeStatus::Partial)
        ->and($allocation->paidCents)->toBe(30_00)
        ->and($allocation->remainingCents)->toBe(70_00);
});

it('ages an unpaid charge through due and then overdue', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(100_00)->create();

    $statusNow = fn () => $this->ledger->chargeAllocations($this->user)[0]->status;

    expect($statusNow())->toBe(ChargeStatus::Pending);

    // Due after 14 days.
    $this->travel(15)->days();
    expect($statusNow())->toBe(ChargeStatus::Due);

    // Overdue after a further 7 days of grace — 21 in total.
    $this->travel(7)->days();
    expect($statusNow())->toBe(ChargeStatus::Overdue);
});

it('suspends booking only once a charge is genuinely overdue', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(100_00)->create();

    expect($this->ledger->hasOverdueCharges($this->user))->toBeFalse();

    $this->travel(22)->days();

    expect($this->ledger->hasOverdueCharges($this->user))->toBeTrue();
});

it('lifts the overdue state when the charge is settled', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(100_00)->create();
    $this->travel(22)->days();

    expect($this->ledger->hasOverdueCharges($this->user))->toBeTrue();

    Payment::factory()->for($this->user)->of(100_00)->confirmed()->create();

    expect($this->ledger->hasOverdueCharges($this->user))->toBeFalse();
});

it('blocks a charge that would breach the carried balance limit', function () {
    $this->user->update(['carried_balance_limit_cents' => 100_00]);
    LedgerEntry::factory()->ownedBy($this->user)->charge(80_00)->create();

    expect($this->ledger->wouldExceedCap($this->user, 20_00))->toBeFalse()
        ->and($this->ledger->wouldExceedCap($this->user, 20_01))->toBeTrue();
});
