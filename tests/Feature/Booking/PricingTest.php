<?php

use App\Models\Asset;
use App\Models\Llc;
use App\Models\Region;
use App\Services\Pricing\PricingService;

beforeEach(function () {
    $this->pricing = app(PricingService::class);
});

function assetWithFees(float $llcPct, float $regionPct, int $llcMin = 0, int $regionMin = 0): Asset
{
    $region = Region::factory()->withFee($regionPct, $regionMin)->create();
    $llc = Llc::factory()->for($region)->withFee($llcPct, $llcMin)->create();

    return Asset::factory()->for($llc)->create()->load('llc.region');
}

it('adds both fees on top of the base price', function () {
    $pricing = $this->pricing->price(assetWithFees(10, 5), basePriceCents: 100_00);

    expect($pricing->llcFeeCents)->toBe(10_00)
        ->and($pricing->regionFeeCents)->toBe(5_00)
        ->and($pricing->totalCents)->toBe(115_00);
});

it('applies the per-meso multiplier as a percentage uplift', function () {
    $pricing = $this->pricing->price(assetWithFees(0, 0), basePriceCents: 100_00, multiplierPct: 50);

    expect($pricing->adjustedCents)->toBe(150_00)
        ->and($pricing->totalCents)->toBe(150_00);
});

it('charges the flat minimum when it beats the percentage', function () {
    // 1% of 100.00 is 1.00, but the minimum is 3.00.
    $pricing = $this->pricing->price(assetWithFees(1, 1, llcMin: 3_00, regionMin: 3_00), basePriceCents: 100_00);

    expect($pricing->llcFeeCents)->toBe(3_00)
        ->and($pricing->regionFeeCents)->toBe(3_00);
});

it('gives LLCs the same flat minimum regions have', function () {
    $pricing = $this->pricing->price(assetWithFees(0, 0, llcMin: 2_50, regionMin: 2_50), basePriceCents: 10_00);

    expect($pricing->llcFeeCents)->toBe(2_50)
        ->and($pricing->regionFeeCents)->toBe(2_50);
});

it('still charges a fee on a free booking, computed on the floor', function () {
    // 10% of the $1.00 floor rather than 10% of nothing.
    $pricing = $this->pricing->price(assetWithFees(10, 0), basePriceCents: 0);

    expect($pricing->feeBaseCents)->toBe(1_00)
        ->and($pricing->llcFeeCents)->toBe(10)
        ->and($pricing->totalCents)->toBe(10);
});

it('clamps a fee percentage above the permitted maximum', function () {
    // Configured at 40%, but fees may not exceed 10%.
    $pricing = $this->pricing->price(assetWithFees(40, 0), basePriceCents: 100_00);

    expect($pricing->llcFeeCents)->toBe(10_00);
});

it('charges fees per person rather than on the group total', function () {
    $asset = assetWithFees(10, 0);
    $pricing = $this->pricing->price($asset, basePriceCents: 100_00, groupSize: 4);

    expect($pricing->groupTotalCents)->toBe(400_00)
        ->and($pricing->perPersonCents)->toBe(100_00)
        ->and($pricing->llcFeeCents)->toBe(10_00);
});

it('scales a group by the configured multiplier', function () {
    $region = Region::factory()->create();
    $llc = Llc::factory()->for($region)->create();
    $asset = Asset::factory()->for($llc)
        ->withSettings(['group_price_mode' => 'multiplier', 'group_multiplier' => 0.5])
        ->create()->load('llc.region');

    // Half price each, four of them.
    $pricing = $this->pricing->price($asset, basePriceCents: 100_00, groupSize: 4);

    expect($pricing->groupTotalCents)->toBe(200_00)
        ->and($pricing->perPersonCents)->toBe(50_00);
});

it('charges a premium for each additional person', function () {
    $region = Region::factory()->create();
    $llc = Llc::factory()->for($region)->create();
    $asset = Asset::factory()->for($llc)
        ->withSettings(['group_price_mode' => 'premium', 'group_premium_cents' => 10_00])
        ->create()->load('llc.region');

    // 100.00 base plus 10.00 for each of the three extra people.
    $pricing = $this->pricing->price($asset, basePriceCents: 100_00, groupSize: 4);

    expect($pricing->groupTotalCents)->toBe(130_00);
});

it('refuses contributions that would total more than the booking', function () {
    expect(fn () => $this->pricing->assertContributionsValid(70, 40))
        ->toThrow(InvalidArgumentException::class, 'exceeds');
});

it('leaves the owner exactly what the contributions do not take', function () {
    $asset = Asset::factory()->contributing(llcPct: 25, regionPct: 15)->create();

    $split = $this->pricing->contributionSplit($asset, 100_00);

    expect($split['llc_cents'])->toBe(25_00)
        ->and($split['region_cents'])->toBe(15_00)
        ->and($split['owner_cents'])->toBe(60_00);
});
