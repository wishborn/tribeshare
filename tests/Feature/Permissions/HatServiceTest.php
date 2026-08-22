<?php

use App\Enums\HatType;
use App\Exceptions\HatChangeRefused;
use App\Models\Asset;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\User;
use App\Services\Permissions\HatService;

beforeEach(function () {
    $this->hats = app(HatService::class);
    $this->user = User::factory()->create();
});

it('writes one row per grant rather than materialising the hierarchy', function () {
    $asset = Asset::factory()->create();

    $this->hats->grant($this->user, HatType::AssetOwner, $asset);

    expect($this->user->hats()->count())->toBe(1);
});

it('treats a granted hat as implying every lesser one at that scope', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::AssetOwner, $asset);

    expect($this->hats->holds($this->user, HatType::AssetManager, $asset))->toBeTrue()
        ->and($this->hats->holds($this->user, HatType::AssetAdmin, $asset))->toBeTrue()
        ->and($this->hats->holds($this->user, HatType::AssetPoolMember, $asset))->toBeTrue();
});

it('does not let asset standing imply anything about the LLC', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::AssetOwner, $asset);

    // The escalation path the prototype had, and the one this closes.
    expect($this->hats->holds($this->user, HatType::LlcAdmin, $asset->llc))->toBeFalse();
});

it('refuses a hat the member already effectively holds', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::AssetOwner, $asset);

    expect(fn () => $this->hats->grant($this->user, HatType::AssetAdmin, $asset))
        ->toThrow(HatChangeRefused::class, 'already holds');
});

it('adds pool access when asset standing is granted', function () {
    $asset = Asset::factory()->create();

    $this->hats->grant($this->user, HatType::AssetManager, $asset);

    expect($asset->poolMembers()->whereKey($this->user->id)->exists())->toBeTrue();
});

it('makes the first owner the asset main owner, and leaves them there', function () {
    $asset = Asset::factory()->create(['main_owner_id' => null]);
    $second = User::factory()->create();

    $this->hats->grant($this->user, HatType::AssetOwner, $asset);
    $this->hats->grant($second, HatType::AssetOwner, $asset);

    expect($asset->refresh()->main_owner_id)->toBe($this->user->id);
});

it('makes the first admin the super admin, and only the first', function () {
    $second = User::factory()->create();

    $this->hats->grant($this->user, HatType::Admin);
    $this->hats->grant($second, HatType::Admin);

    expect($this->user->refresh()->is_super_admin)->toBeTrue()
        ->and($second->refresh()->is_super_admin)->toBeFalse();
});

it('creates a pending hat inert', function () {
    $llc = Llc::factory()->create();

    $hat = $this->hats->grant($this->user, HatType::LlcMember, $llc, pending: true);

    expect($hat->active)->toBeFalse()
        ->and($this->hats->holds($this->user, HatType::LlcMember, $llc))->toBeFalse();
});

// --- The two guards -----------------------------------------------------

it('refuses to remove a members last membership', function () {
    $llc = Llc::factory()->create();
    $hat = $this->hats->grant($this->user, HatType::LlcMember, $llc);

    expect(fn () => $this->hats->revoke($hat))
        ->toThrow(HatChangeRefused::class, 'no membership');
});

it('allows removing a membership when another remains', function () {
    $first = Llc::factory()->create();
    $second = Llc::factory()->create();
    $hat = $this->hats->grant($this->user, HatType::LlcMember, $first);
    $this->hats->grant($this->user, HatType::LlcMember, $second);

    $this->hats->revoke($hat);

    expect(Hat::query()->whereKey($hat->id)->exists())->toBeFalse();
});

it('refuses to remove the only owner of an entity', function () {
    $llc = Llc::factory()->create();
    $hat = $this->hats->grant($this->user, HatType::LlcOwner, $llc);

    expect(fn () => $this->hats->revoke($hat))
        ->toThrow(HatChangeRefused::class, 'only active');
});

it('allows removing an owner once another exists', function () {
    $llc = Llc::factory()->create();
    $hat = $this->hats->grant($this->user, HatType::LlcOwner, $llc);
    $this->hats->grant(User::factory()->create(), HatType::LlcOwner, $llc);

    $this->hats->revoke($hat);

    expect(Hat::query()->whereKey($hat->id)->exists())->toBeFalse();
});

// --- Demotion -----------------------------------------------------------

it('demotes by granting the lesser hat before removing the greater', function () {
    $asset = Asset::factory()->create();
    $owner = $this->hats->grant($this->user, HatType::AssetOwner, $asset);
    // A second owner, so the sole-owner guard does not block the demotion.
    $this->hats->grant(User::factory()->create(), HatType::AssetOwner, $asset);

    $this->hats->demote($owner, HatType::AssetManager);

    expect($this->hats->holds($this->user, HatType::AssetManager, $asset))->toBeTrue()
        ->and($this->hats->holds($this->user, HatType::AssetOwner, $asset))->toBeFalse();
});

it('refuses to demote to a hat the original does not imply', function () {
    $asset = Asset::factory()->create();
    $admin = $this->hats->grant($this->user, HatType::AssetAdmin, $asset);

    expect(fn () => $this->hats->demote($admin, HatType::AssetOwner))
        ->toThrow(HatChangeRefused::class, 'ranked below');
});

it('reports standing over an entity as the highest applicable rank', function () {
    $asset = Asset::factory()->create();
    $this->hats->grant($this->user, HatType::AssetManager, $asset);

    expect($this->hats->rankFor($this->user, $asset))->toBe(HatType::AssetManager->rank());
});

it('refuses to strip the super admin of the admin hat', function () {
    $super = User::factory()->create();
    $hat = $this->hats->grant($super, HatType::Admin);

    $second = User::factory()->create();
    $this->hats->grant($second, HatType::Admin);

    // Absolute, like the other two: HatPolicy refuses it as well, but policy
    // decides authority and the service decides possibility. The platform
    // must never be left without the account that can appoint Admins.
    expect(fn () => $this->hats->revoke($hat->refresh()))
        ->toThrow(HatChangeRefused::class, 'Super Admin');
});
