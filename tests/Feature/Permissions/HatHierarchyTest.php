<?php

use App\Enums\HatType;

/**
 * The hierarchy is IMPLIED rather than materialised: granting a hat writes
 * one row, and holding it means holding everything beneath it in the same
 * family. These are the rules authorization compares against.
 */
it('ranks the platform operator above the regional controller', function () {
    expect(HatType::Admin->rank())->toBeGreaterThan(HatType::Rcm->rank())
        ->and(HatType::Rcm->rank())->toBeGreaterThan(HatType::RegionOwner->rank())
        ->and(HatType::RegionOwner->rank())->toBeGreaterThan(HatType::LlcOwner->rank());
});

it('implies every lesser hat in the same family', function () {
    expect(HatType::AssetOwner->implies(HatType::AssetManager))->toBeTrue()
        ->and(HatType::AssetOwner->implies(HatType::AssetAdmin))->toBeTrue()
        ->and(HatType::AssetOwner->implies(HatType::AssetPoolMember))->toBeTrue()
        ->and(HatType::LlcOwner->implies(HatType::LlcMember))->toBeTrue();
});

it('does not imply upward', function () {
    expect(HatType::AssetAdmin->implies(HatType::AssetOwner))->toBeFalse()
        ->and(HatType::LlcMember->implies(HatType::LlcOwner))->toBeFalse();
});

it('never implies across families', function () {
    // The escalation path the prototype had, and the one this rules out:
    // authority over an asset is not authority over its LLC.
    expect(HatType::AssetOwner->implies(HatType::LlcAdmin))->toBeFalse()
        ->and(HatType::AssetManager->implies(HatType::LlcMember))->toBeFalse()
        // And the reverse, which the prototype also denied.
        ->and(HatType::LlcOwner->implies(HatType::AssetOwner))->toBeFalse();
});

it('treats a hat as implying itself', function () {
    expect(HatType::AssetManager->implies(HatType::AssetManager))->toBeTrue();
});

it('keeps memberships and roles on one ladder', function () {
    // Membership IS a hat — which is why one table serves both.
    expect(HatType::LlcMember->isMembership())->toBeTrue()
        ->and(HatType::LlcOwner->isMembership())->toBeFalse()
        ->and(HatType::LlcOwner->rank())->toBeGreaterThan(HatType::LlcMember->rank());
});

it('gives booking priority to direct asset standing only', function () {
    expect(HatType::AssetOwner->bookingPriority())->toBe(4)
        ->and(HatType::AssetManager->bookingPriority())->toBe(3)
        ->and(HatType::AssetAdmin->bookingPriority())->toBe(2)
        // An LLC Owner holds no asset standing, so books as a pool member.
        ->and(HatType::LlcOwner->bookingPriority())->toBe(1);
});
