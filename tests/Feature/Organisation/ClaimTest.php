<?php

use App\Enums\ClaimStatus;
use App\Enums\HatType;
use App\Enums\RegionDocumentCategory;
use App\Models\Asset;
use App\Models\Llc;
use App\Models\Region;
use App\Models\RegionDocument;
use App\Models\User;
use App\Services\Organisation\ClaimService;
use App\Services\Permissions\HatService;

beforeEach(function () {
    $this->claims = app(ClaimService::class);
    $this->region = Region::factory()->create();
    $this->rcm = User::factory()->create();
    app(HatService::class)->grant($this->rcm, HatType::Rcm, $this->region);
});

it('files a claim with the step that opened it recorded', function () {
    $claim = $this->claims->file(
        $this->region,
        $this->rcm,
        'Storm damage to the boathouse',
        now()->subWeek(),
        claimedCents: 4_500_00,
    );

    expect($claim->status)->toBe(ClaimStatus::Filed)
        ->and($claim->claimed_cents)->toBe(4_500_00)
        // The prototype moved a claim along by overwriting a status string,
        // so nothing recorded when it changed or who changed it.
        ->and($claim->events()->count())->toBe(1)
        ->and($claim->events()->sole()->to_status)->toBe(ClaimStatus::Filed);
});

it('walks a claim through its life', function () {
    $claim = $this->claims->file($this->region, $this->rcm, 'Storm damage', now()->subWeek(), 4_500_00);

    $this->claims->advance($claim, ClaimStatus::UnderReview, $this->rcm);
    $this->claims->advance($claim->refresh(), ClaimStatus::Approved, $this->rcm);
    $this->claims->advance($claim->refresh(), ClaimStatus::Paid, $this->rcm, settledCents: 4_100_00);

    $claim->refresh();

    expect($claim->status)->toBe(ClaimStatus::Paid)
        ->and($claim->settled_cents)->toBe(4_100_00)
        ->and($claim->settled_on)->not->toBeNull()
        ->and($claim->events()->count())->toBe(4);
});

it('refuses a step the claim cannot take', function () {
    $claim = $this->claims->file($this->region, $this->rcm, 'Storm damage', now()->subWeek());

    // A claim cannot go from filed straight to paid, which the prototype's
    // status string happily allowed.
    expect(fn () => $this->claims->advance($claim, ClaimStatus::Paid, $this->rcm))
        ->toThrow(RuntimeException::class, 'cannot become paid');
});

it('settles for what was claimed when no figure is given', function () {
    $claim = $this->claims->file($this->region, $this->rcm, 'Storm damage', now()->subWeek(), 900_00);

    $this->claims->advance($claim, ClaimStatus::UnderReview, $this->rcm);
    $this->claims->advance($claim->refresh(), ClaimStatus::Approved, $this->rcm);
    $this->claims->advance($claim->refresh(), ClaimStatus::Paid, $this->rcm);

    expect($claim->refresh()->settled_cents)->toBe(900_00);
});

it('knows which claims are still open', function () {
    $open = $this->claims->file($this->region, $this->rcm, 'Open', now()->subWeek());
    $closed = $this->claims->file($this->region, $this->rcm, 'Closed', now()->subMonth());

    $this->claims->advance($closed, ClaimStatus::Closed, $this->rcm);

    expect($this->region->claims()->open()->pluck('id')->all())->toBe([$open->id]);
});

it('files a claim against the asset it concerns', function () {
    $llc = Llc::factory()->for($this->region)->create();
    $asset = Asset::factory()->for($llc)->create();

    $claim = $this->claims->file(
        $this->region,
        $this->rcm,
        'Hull damage',
        now()->subDays(3),
        subject: $asset,
    );

    expect($claim->subject->is($asset))->toBeTrue();
});

it('attaches paperwork from the region library', function () {
    $claim = $this->claims->file($this->region, $this->rcm, 'Storm damage', now()->subWeek());

    $document = RegionDocument::create([
        'region_id' => $this->region->id,
        'category' => RegionDocumentCategory::Claims,
        'title' => 'Adjuster report',
        'path' => 'documents/adjuster.pdf',
        'original_name' => 'adjuster.pdf',
        'uploaded_by' => $this->rcm->id,
    ]);

    $this->claims->attachDocument($claim, $document->id);

    expect($claim->documents()->count())->toBe(1)
        ->and($document->claims()->count())->toBe(1);
});

it('lets whoever manages the documents manage the claims', function () {
    $owner = User::factory()->create();
    app(HatService::class)->grant($owner, HatType::RegionOwner, $this->region);
    $member = User::factory()->create();

    expect($owner->can('manageClaims', $this->region))->toBeTrue()
        ->and($this->rcm->can('manageClaims', $this->region))->toBeTrue()
        ->and($member->can('manageClaims', $this->region))->toBeFalse();
});
