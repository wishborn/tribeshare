<?php

use App\Models\LedgerEntry;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();
});

it('offers nothing to a member with no credit', function () {
    $availability = $this->ledger->creditAvailability($this->user);

    expect($availability->totalCents)->toBe(0)
        ->and($availability->availableCents)->toBe(0)
        ->and($availability->upcoming)->toBeEmpty();
});

it('holds credit back until the income behind it has matured', function () {
    LedgerEntry::factory()->ownedBy($this->user)->income(40_00)->agedDays(2)->create();

    $availability = $this->ledger->creditAvailability($this->user);

    expect($availability->totalCents)->toBe(40_00)
        ->and($availability->availableCents)->toBe(0)
        ->and($availability->hasMaturingCredit())->toBeTrue();
});

it('releases credit once the income is old enough', function () {
    LedgerEntry::factory()->ownedBy($this->user)->income(40_00)->agedDays(8)->create();

    $availability = $this->ledger->creditAvailability($this->user);

    expect($availability->availableCents)->toBe(40_00)
        ->and($availability->upcoming)->toBeEmpty();
});

it('splits credit between what is payable now and what is still maturing', function () {
    LedgerEntry::factory()->ownedBy($this->user)->income(30_00)->agedDays(10)->create();
    LedgerEntry::factory()->ownedBy($this->user)->income(20_00)->agedDays(1)->create();

    $availability = $this->ledger->creditAvailability($this->user);

    expect($availability->totalCents)->toBe(50_00)
        ->and($availability->availableCents)->toBe(30_00)
        ->and($availability->upcoming)->toHaveCount(1)
        ->and($availability->upcoming[0]['amount_cents'])->toBe(20_00);
});

it('allows a payout request when matured credit exists', function () {
    LedgerEntry::factory()->ownedBy($this->user)->income(40_00)->agedDays(8)->create();

    expect($this->ledger->canRequestPayout($this->user))->toBeTrue();
});

it('refuses a second request while one is still open', function () {
    LedgerEntry::factory()->ownedBy($this->user)->income(40_00)->agedDays(8)->create();
    PayoutRequest::factory()->for($this->user)->create();

    expect($this->ledger->canRequestPayout($this->user))->toBeFalse();
});

it('enforces one open request per member at the database level', function () {
    PayoutRequest::factory()->for($this->user)->create();

    expect(fn () => PayoutRequest::factory()->for($this->user)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});
