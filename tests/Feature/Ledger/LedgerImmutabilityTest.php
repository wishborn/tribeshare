<?php

use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\Ledger\LedgerService;

beforeEach(function () {
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();
});

it('refuses to update a posted entry', function () {
    $entry = LedgerEntry::factory()->ownedBy($this->user)->charge(50_00)->create();

    expect(fn () => $entry->update(['amount_cents' => 1]))
        ->toThrow(RuntimeException::class, 'immutable');
});

it('refuses to delete a posted entry', function () {
    $entry = LedgerEntry::factory()->ownedBy($this->user)->charge(50_00)->create();

    expect(fn () => $entry->delete())
        ->toThrow(RuntimeException::class, 'immutable');
});

it('corrects a charge by posting its mirror image', function () {
    $charge = LedgerEntry::factory()->ownedBy($this->user)->charge(50_00)->create();

    expect($this->ledger->netBalanceCents($this->user))->toBe(50_00);

    $reversal = $this->ledger->reverse($charge, 'Charged in error');

    expect($this->ledger->netBalanceCents($this->user))->toBe(0)
        ->and($reversal->direction)->toBe(LedgerDirection::Credit)
        ->and($reversal->label)->toBe(LedgerLabel::Reversal)
        ->and($reversal->reverses_id)->toBe($charge->id)
        ->and($reversal->reason)->toBe('Charged in error');

    // History survives the correction.
    expect(LedgerEntry::query()->ownedBy($this->user)->count())->toBe(2);
});

it('rejects a negative amount rather than flipping its direction', function () {
    expect(fn () => $this->ledger->post(
        $this->user, LedgerDirection::Debit, LedgerLabel::AssetCharge, -100
    ))->toThrow(InvalidArgumentException::class);
});

it('derives the month key from the entry timestamp', function () {
    $entry = LedgerEntry::factory()->ownedBy($this->user)->charge(10_00)
        ->create(['created_at' => '2026-03-17 09:00:00', 'month_key' => 'wrong!']);

    expect($entry->month_key)->toBe('2026-03');
});
