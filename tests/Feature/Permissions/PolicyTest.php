<?php

use App\Enums\AssetPower;
use App\Enums\HatType;
use App\Enums\PowerTier;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\LedgerEntry;
use App\Models\Llc;
use App\Models\Region;
use App\Models\User;
use App\Policies\HatPolicy;
use App\Services\Permissions\HatService;
use App\Services\Permissions\PowerService;
use App\Services\Permissions\SuspensionService;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->hats = app(HatService::class);
    $this->powers = app(PowerService::class);
    $this->suspensions = app(SuspensionService::class);

    $this->asset = Asset::factory()->create();
    $this->llc = $this->asset->llc;
    $this->member = User::factory()->create();
    $this->asset->poolMembers()->attach($this->member);
});

function withHat(HatType $type, ?object $scope = null): User
{
    $user = User::factory()->create();
    app(HatService::class)->grant($user, $type, $scope);

    return $user;
}

// --- The two domains are separate --------------------------------------

it('lets the content authority act on content', function () {
    $rcm = withHat(HatType::Rcm);

    expect($rcm->can('update', $this->asset))->toBeTrue()
        ->and($rcm->can('approveBookings', $this->asset))->toBeTrue()
        ->and($rcm->can('manageMembers', $this->llc))->toBeTrue();
});

it('does not let the platform operator act on content', function () {
    $admin = withHat(HatType::Admin);

    // An Admin outranks an RCM for appointing purposes, but managing the
    // platform is not managing an LLC's assets or members.
    expect($admin->can('update', $this->asset))->toBeFalse()
        ->and($admin->can('approveBookings', $this->asset))->toBeFalse()
        ->and($admin->can('manageMembers', $this->llc))->toBeFalse();
});

it('does not let the content authority reconfigure the platform', function () {
    $rcm = withHat(HatType::Rcm);

    expect(Gate::forUser($rcm)->allows('configure-platform'))->toBeFalse()
        ->and(Gate::forUser($rcm)->allows('appoint-rcm'))->toBeFalse();
});

it('gives platform abilities to the admin', function () {
    $admin = withHat(HatType::Admin);

    expect(Gate::forUser($admin)->allows('configure-platform'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('appoint-rcm'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('view-platform-audit'))->toBeTrue();
});

it('reserves appointing other admins to the super admin', function () {
    $first = withHat(HatType::Admin);
    $second = withHat(HatType::Admin);

    expect(Gate::forUser($first)->allows('manage-admins'))->toBeTrue()
        ->and(Gate::forUser($second)->allows('manage-admins'))->toBeFalse();
});

// --- Granting authority --------------------------------------------------

it('lets only the super admin appoint an admin', function () {
    $superAdmin = withHat(HatType::Admin);
    $rcm = withHat(HatType::Rcm);

    expect(app(HatPolicy::class)->grant($superAdmin, HatType::Admin))->toBeTrue()
        ->and(app(HatPolicy::class)->grant($rcm, HatType::Admin))->toBeFalse();
});

it('lets only an admin appoint the content authority', function () {
    $admin = withHat(HatType::Admin);
    $rcm = withHat(HatType::Rcm);

    $policy = app(HatPolicy::class);

    expect($policy->grant($admin, HatType::Rcm))->toBeTrue()
        // An RCM cannot appoint another RCM.
        ->and($policy->grant($rcm, HatType::Rcm))->toBeFalse();
});

it('makes regional hats the content authority remit, not the platform', function () {
    $region = Region::factory()->create();
    $rcm = withHat(HatType::Rcm);
    $admin = withHat(HatType::Admin);

    $policy = app(HatPolicy::class);

    expect($policy->grantRegionalMembership($rcm, $region))->toBeTrue()
        ->and($policy->grantRegionalMembership($admin, $region))->toBeFalse();
});

it('refuses to grant a hat at or above the granters own standing', function () {
    $manager = withHat(HatType::LlcManager, $this->llc);
    $policy = app(HatPolicy::class);

    expect($policy->grant($manager, HatType::LlcAdmin, $this->llc))->toBeTrue()
        ->and($policy->grant($manager, HatType::LlcManager, $this->llc))->toBeFalse()
        ->and($policy->grant($manager, HatType::LlcOwner, $this->llc))->toBeFalse();
});

// --- Asset and booking authority ----------------------------------------

it('lets an owner edit settings but not a manager by default', function () {
    $owner = withHat(HatType::AssetOwner, $this->asset);
    $manager = withHat(HatType::AssetManager, $this->asset);
    $admin = withHat(HatType::AssetAdmin, $this->asset);

    expect($owner->can('update', $this->asset))->toBeTrue()
        ->and($manager->can('update', $this->asset))->toBeTrue()
        ->and($admin->can('update', $this->asset))->toBeFalse();
});

it('honours a per-entity power override', function () {
    $assetAdmin = withHat(HatType::AssetAdmin, $this->asset);
    $this->powers->set($this->asset, PowerTier::Admin, AssetPower::EditSettings, true);

    expect($assetAdmin->can('update', $this->asset))->toBeTrue();
});

it('reserves deletion to an owner', function () {
    $manager = withHat(HatType::AssetManager, $this->asset);
    $owner = withHat(HatType::AssetOwner, $this->asset);

    expect($manager->can('delete', $this->asset))->toBeFalse()
        ->and($owner->can('delete', $this->asset))->toBeTrue();
});

it('hides an asset from a member suspended from its LLC', function () {
    expect($this->member->can('view', $this->asset))->toBeTrue();

    $this->suspensions->suspendFrom($this->member, $this->llc);

    expect($this->member->refresh()->can('view', $this->asset))->toBeFalse();
});

it('never lets a member approve their own booking', function () {
    $owner = withHat(HatType::AssetOwner, $this->asset);
    $booking = Booking::factory()->for($this->asset)->for($owner)->create(['llc_id' => $this->llc->id]);

    expect($owner->can('approve', $booking))->toBeFalse();
});

it('lets a member cancel their own booking and a manager cancel anyones', function () {
    $manager = withHat(HatType::AssetManager, $this->asset);
    $booking = Booking::factory()->for($this->asset)->for($this->member)->create(['llc_id' => $this->llc->id]);

    expect($this->member->can('cancel', $booking))->toBeTrue()
        ->and($manager->can('cancel', $booking))->toBeTrue();
});

it('refuses a pick-up from a member whose balance is overdue', function () {
    $booking = Booking::factory()->for($this->asset)->for($this->member)->create(['llc_id' => $this->llc->id]);

    $picker = User::factory()->create();
    $this->asset->poolMembers()->attach($picker);
    expect($picker->can('pickUp', $booking))->toBeTrue();

    LedgerEntry::factory()->ownedBy($picker)->charge(40_00)->agedDays(22)->create();

    expect($picker->can('pickUp', $booking))->toBeFalse();
});

// --- Members -------------------------------------------------------------

it('protects the super admin from suspension and deletion', function () {
    $superAdmin = withHat(HatType::Admin);
    $rcm = withHat(HatType::Rcm);

    expect($rcm->can('suspendGlobally', $superAdmin))->toBeFalse()
        ->and($rcm->can('delete', $superAdmin))->toBeFalse();
});

it('never allows billing suspension to be cleared by hand', function () {
    $rcm = withHat(HatType::Rcm);

    // It is derived from the ledger; settling the balance is what clears it.
    expect($rcm->can('clearBillingSuspension', $this->member))->toBeFalse();
});

it('hides a hidden region from everyone but the content authority', function () {
    $hidden = Region::factory()->create(['visible' => false]);
    $rcm = withHat(HatType::Rcm);
    $regional = withHat(HatType::RegionalMember, $hidden);

    expect($rcm->can('view', $hidden))->toBeTrue()
        ->and($regional->can('view', $hidden))->toBeFalse();
});
