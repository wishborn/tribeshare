<?php

use App\Enums\AssetPower;
use App\Enums\HatType;
use App\Enums\LlcPower;
use App\Enums\PowerTier;
use App\Models\Asset;
use App\Models\LedgerEntry;
use App\Models\Llc;
use App\Models\User;
use App\Services\Permissions\HatService;
use App\Services\Permissions\PowerService;
use App\Services\Permissions\SuspensionService;

beforeEach(function () {
    $this->hats = app(HatService::class);
    $this->powers = app(PowerService::class);
    $this->suspensions = app(SuspensionService::class);
    $this->user = User::factory()->create();
});

// --- Delegated powers ---------------------------------------------------

it('gives an owner every power without consulting the table', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::AssetOwner, $asset);

    foreach (AssetPower::cases() as $power) {
        expect($this->powers->onAsset($this->user, $asset, $power))->toBeTrue();
    }
});

it('applies the shipped defaults to a manager', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::AssetManager, $asset);

    expect($this->powers->onAsset($this->user, $asset, AssetPower::PublishCalendar))->toBeTrue()
        // Owner-only for both tiers, and the entry exists to say so.
        ->and($this->powers->onAsset($this->user, $asset, AssetPower::AssignAssetHats))->toBeFalse();
});

it('gives an admin the routine half only', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::AssetAdmin, $asset);

    expect($this->powers->onAsset($this->user, $asset, AssetPower::ApproveBookings))->toBeTrue()
        ->and($this->powers->onAsset($this->user, $asset, AssetPower::PublishCalendar))->toBeFalse()
        ->and($this->powers->onAsset($this->user, $asset, AssetPower::EditSettings))->toBeFalse();
});

it('lets an entity override a default', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::AssetAdmin, $asset);

    $this->powers->set($asset, PowerTier::Admin, AssetPower::PublishCalendar, true);

    expect($this->powers->onAsset($this->user, $asset, AssetPower::PublishCalendar))->toBeTrue();
});

it('does not let an asset hat confer LLC powers', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::AssetManager, $asset);

    // The prototype's escalation path: one asset hat granting LLC-wide
    // delegated powers. Dropped.
    expect($this->powers->onLlc($this->user, $asset->llc, LlcPower::ManagePools))->toBeFalse()
        ->and($this->powers->onLlc($this->user, $asset->llc, LlcPower::ApproveAppraisals))->toBeFalse();
});

it('does not let an LLC hat confer asset powers', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::LlcOwner, $asset->llc);

    expect($this->powers->onAsset($this->user, $asset, AssetPower::ApproveBookings))->toBeFalse();
});

it('requires an exactly-scoped hat for the two sensitive LLC powers', function () {
    $llc = Llc::factory()->create();
    // A globally-scoped manager hat — satisfies ordinary checks.
    $this->hats->grant($this->user, HatType::LlcManager);

    expect($this->powers->onLlc($this->user, $llc, LlcPower::ManagePools))->toBeTrue()
        ->and($this->powers->onLlc($this->user, $llc, LlcPower::ManageMembers))->toBeFalse()
        ->and($this->powers->onLlc($this->user, $llc, LlcPower::AssignHats))->toBeFalse();
});

it('lets the content authority short-circuit every power check', function () {
    $asset = Asset::factory()->create();
    $rcm = User::factory()->create();
    $this->hats->grant($rcm, HatType::Rcm);

    expect($this->powers->onAsset($rcm, $asset, AssetPower::EditSettings))->toBeTrue()
        ->and($this->powers->onLlc($rcm, $asset->llc, LlcPower::ManageMembers))->toBeTrue();
});

it('does not give the platform operator any content power', function () {
    $asset = Asset::factory()->create();
    $admin = User::factory()->create();
    $this->hats->grant($admin, HatType::Admin);

    // The two tiers are separate domains, not nested ones. An Admin outranks
    // an RCM for appointing purposes and holds nothing over an LLC's assets.
    expect($this->powers->onAsset($admin, $asset, AssetPower::EditSettings))->toBeFalse()
        ->and($this->powers->onLlc($admin, $asset->llc, LlcPower::ManageMembers))->toBeFalse();
});

// --- Suspension ---------------------------------------------------------

it('bars a globally suspended member everywhere', function () {
    $llc = Llc::factory()->create();
    $this->suspensions->suspendGlobally($this->user);

    expect($this->suspensions->isSuspendedGlobally($this->user))->toBeTrue()
        ->and($this->suspensions->isSuspendedFrom($this->user, $llc))->toBeTrue();
});

it('bars a scoped suspension only within its own LLC', function () {
    $barred = Llc::factory()->create();
    $other = Llc::factory()->create();
    $this->suspensions->suspendFrom($this->user, $barred);

    expect($this->suspensions->isSuspendedFrom($this->user, $barred))->toBeTrue()
        ->and($this->suspensions->isSuspendedFrom($this->user, $other))->toBeFalse()
        ->and($this->suspensions->isSuspendedGlobally($this->user))->toBeFalse();
});

it('records who lifted a suspension rather than deleting it', function () {
    $llc = Llc::factory()->create();
    $rcm = User::factory()->create();
    $suspension = $this->suspensions->suspendFrom($this->user, $llc);

    $this->suspensions->lift($suspension, $rcm);

    expect($this->suspensions->isSuspendedFrom($this->user, $llc))->toBeFalse()
        // The history survives.
        ->and($this->user->suspensions()->count())->toBe(1)
        ->and($suspension->refresh()->lifted_by)->toBe($rcm->id);
});

it('derives billing suspension from the ledger, not a flag', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(50_00)->agedDays(22)->create();

    // The cached flag still says otherwise until it is rebuilt.
    expect($this->user->billing_suspended)->toBeFalse()
        ->and($this->suspensions->isBillingSuspended($this->user))->toBeTrue();

    $this->suspensions->refreshBillingSuspension($this->user);

    expect($this->user->refresh()->billing_suspended)->toBeTrue();
});

it('keeps billing suspension separate from the other two', function () {
    LedgerEntry::factory()->ownedBy($this->user)->charge(50_00)->agedDays(22)->create();

    // Owing money does not make a member suspended in the hat sense.
    expect($this->suspensions->isBillingSuspended($this->user))->toBeTrue()
        ->and($this->suspensions->isSuspendedAnywhere($this->user))->toBeFalse();
});
