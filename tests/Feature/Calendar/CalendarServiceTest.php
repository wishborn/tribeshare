<?php

use App\Models\Asset;
use App\Models\Calendar;
use App\Models\CalendarRule;
use App\Models\CalendarRulePriority;
use App\Models\User;
use App\Services\Calendar\CalendarService;
use Carbon\Carbon;

beforeEach(function () {
    $this->calendars = app(CalendarService::class);
    $this->asset = Asset::factory()->create();
});

/**
 * A published month with one rule covering the whole of the given day.
 */
function publishedDay(Asset $asset, string $month, int $day, float $multiplier = 100, bool $bookable = true): CalendarRule
{
    $calendar = Calendar::factory()->for_($asset, $month)->published()->create();

    return CalendarRule::factory()
        ->for($calendar)
        ->wholeDay($day)
        ->pricedAt($multiplier)
        ->create(['bookable' => $bookable]);
}

it('refuses a range in a month that is not published', function () {
    Calendar::factory()->for_($this->asset, '2026-09')->create();

    $resolution = $this->calendars->resolve(
        $this->asset,
        Carbon::parse('2026-09-01 10:00'),
        Carbon::parse('2026-09-01 12:00'),
    );

    expect($resolution->bookable)->toBeFalse()
        ->and($resolution->reasons)->toContain('The calendar for 2026-09 is not published.');
});

it('refuses a range with no rule, because rules opt slices in', function () {
    Calendar::factory()->for_($this->asset, '2026-09')->published()->create();

    $resolution = $this->calendars->resolve(
        $this->asset,
        Carbon::parse('2026-09-01 10:00'),
        Carbon::parse('2026-09-01 12:00'),
    );

    // Published but empty sells nothing — an unruled slice is closed.
    expect($resolution->bookable)->toBeFalse()
        ->and($resolution->mesosCovered)->toBe(0)
        ->and($resolution->mesosRequested)->toBe(20);
});

it('allows a fully ruled range and reports its multiplier', function () {
    publishedDay($this->asset, '2026-09', 1, multiplier: 150);

    $resolution = $this->calendars->resolve(
        $this->asset,
        Carbon::parse('2026-09-01 10:00'),
        Carbon::parse('2026-09-01 12:00'),
    );

    expect($resolution->bookable)->toBeTrue()
        ->and($resolution->isFullyCovered())->toBeTrue()
        ->and($resolution->multiplierPct)->toBe(150.0);
});

it('refuses a range only partly covered by rules', function () {
    $calendar = Calendar::factory()->for_($this->asset, '2026-09')->published()->create();
    // Covers 10:00–11:00 only; the booking asks for two hours.
    CalendarRule::factory()->for($calendar)->covering(1, 100, 110)->create();

    $resolution = $this->calendars->resolve(
        $this->asset,
        Carbon::parse('2026-09-01 10:00'),
        Carbon::parse('2026-09-01 12:00'),
    );

    expect($resolution->bookable)->toBeFalse()
        ->and($resolution->mesosCovered)->toBe(10)
        ->and($resolution->mesosRequested)->toBe(20);
});

it('refuses a range a rule explicitly closes', function () {
    publishedDay($this->asset, '2026-09', 1, bookable: false);

    $resolution = $this->calendars->resolve(
        $this->asset,
        Carbon::parse('2026-09-01 10:00'),
        Carbon::parse('2026-09-01 12:00'),
    );

    expect($resolution->bookable)->toBeFalse()
        ->and($resolution->reasons)->toContain('Part of this range is marked unbookable.');
});

it('averages the multiplier across a range spanning two rules', function () {
    $calendar = Calendar::factory()->for_($this->asset, '2026-09')->published()->create();
    // 10:00–11:00 at 200, 11:00–12:00 at 100 — ten mesos each.
    CalendarRule::factory()->for($calendar)->covering(1, 100, 110)->pricedAt(200)->create();
    CalendarRule::factory()->for($calendar)->covering(1, 110, 120)->pricedAt(100)->create();

    $resolution = $this->calendars->resolve(
        $this->asset,
        Carbon::parse('2026-09-01 10:00'),
        Carbon::parse('2026-09-01 12:00'),
    );

    expect($resolution->bookable)->toBeTrue()
        ->and($resolution->multiplierPct)->toBe(150.0);
});

it('averages across days for a booking that spans midnight', function () {
    $calendar = Calendar::factory()->for_($this->asset, '2026-09')->published()->create();
    CalendarRule::factory()->for($calendar)->wholeDay(1)->pricedAt(200)->create();
    CalendarRule::factory()->for($calendar)->wholeDay(2)->pricedAt(100)->create();

    // 23:00 to 01:00 — ten mesos on each side of midnight.
    $resolution = $this->calendars->resolve(
        $this->asset,
        Carbon::parse('2026-09-01 23:00'),
        Carbon::parse('2026-09-02 01:00'),
    );

    expect($resolution->bookable)->toBeTrue()
        ->and($resolution->multiplierPct)->toBe(150.0);
});

it('requires every month a booking touches to be published', function () {
    publishedDay($this->asset, '2026-09', 30);
    // October exists but is not published.
    Calendar::factory()->for_($this->asset, '2026-10')->create();

    $resolution = $this->calendars->resolve(
        $this->asset,
        Carbon::parse('2026-09-30 23:00'),
        Carbon::parse('2026-10-01 01:00'),
    );

    expect($resolution->bookable)->toBeFalse()
        ->and($resolution->reasons)->toContain('The calendar for 2026-10 is not published.');
});

// --- Publishing ----------------------------------------------------------

it('promotes draft rules on publish and discards what they replace', function () {
    $calendar = Calendar::factory()->for_($this->asset, '2026-09')->published()->create();
    CalendarRule::factory()->for($calendar)->wholeDay(1)->pricedAt(100)->create();
    CalendarRule::factory()->for($calendar)->wholeDay(1)->pricedAt(200)->draft()->create();

    $this->calendars->publish($calendar->refresh());

    $live = $calendar->publishedRules()->get();

    expect($live)->toHaveCount(1)
        ->and($live->first()->price_multiplier_pct)->toBe(200.0)
        ->and($calendar->draftRules()->count())->toBe(0);
});

it('unpublishes explicitly rather than toggling', function () {
    $calendar = Calendar::factory()->for_($this->asset, '2026-09')->published()->create();

    $this->calendars->unpublish($calendar);
    expect($calendar->refresh()->isPublished())->toBeFalse();

    // Unpublishing again leaves it unpublished, rather than flipping back.
    $this->calendars->unpublish($calendar);
    expect($calendar->refresh()->isPublished())->toBeFalse();
});

it('discards a draft without touching what is live', function () {
    $calendar = Calendar::factory()->for_($this->asset, '2026-09')->published()->create();
    CalendarRule::factory()->for($calendar)->wholeDay(1)->pricedAt(100)->create();
    CalendarRule::factory()->for($calendar)->wholeDay(1)->pricedAt(999)->draft()->create();

    $this->calendars->discardDraft($calendar);

    expect($calendar->draftRules()->count())->toBe(0)
        ->and($calendar->publishedRules()->first()->price_multiplier_pct)->toBe(100.0);
});

// --- Priority ------------------------------------------------------------

it('reports a member barred from a slice', function () {
    $rule = publishedDay($this->asset, '2026-09', 1);
    $member = User::factory()->create();
    CalendarRulePriority::factory()->for($rule, 'rule')->for($member)->barred()->create();

    $standing = $this->calendars->standingFor(
        $member,
        $this->asset,
        Carbon::parse('2026-09-01 10:00'),
        Carbon::parse('2026-09-01 12:00'),
    );

    expect($standing['barred'])->toBeTrue();
});

it('reports a member protected from being bumped', function () {
    $rule = publishedDay($this->asset, '2026-09', 1);
    $member = User::factory()->create();
    CalendarRulePriority::factory()->for($rule, 'rule')->for($member)->unbumpable()->create();

    $standing = $this->calendars->standingFor(
        $member,
        $this->asset,
        Carbon::parse('2026-09-01 10:00'),
        Carbon::parse('2026-09-01 12:00'),
    );

    expect($standing['unbumpable'])->toBeTrue()
        ->and($standing['barred'])->toBeFalse();
});

it('treats members sharing a position as one group', function () {
    $rule = publishedDay($this->asset, '2026-09', 1);
    $first = User::factory()->create();
    $second = User::factory()->create();

    CalendarRulePriority::factory()->for($rule, 'rule')->for($first)->create(['position' => 0]);
    CalendarRulePriority::factory()->for($rule, 'rule')->for($second)->create(['position' => 0]);

    $at = [Carbon::parse('2026-09-01 10:00'), Carbon::parse('2026-09-01 12:00')];

    expect($this->calendars->standingFor($first, $this->asset, ...$at)['position'])
        ->toBe($this->calendars->standingFor($second, $this->asset, ...$at)['position']);
});

// --- Coverage ------------------------------------------------------------

it('counts only future mesos as coverable', function () {
    $month = now()->format('Y-m');
    $calendar = Calendar::factory()->for_($this->asset, $month)->published()->create();

    $before = $this->calendars->coverage($calendar->refresh());

    CalendarRule::factory()->for($calendar)->wholeDay((int) now()->addDay()->format('j'))->create();

    $after = $this->calendars->coverage($calendar->refresh());

    expect($after['covered'])->toBeGreaterThan($before['covered'])
        // A month half gone is not half unfinished.
        ->and($after['total'])->toBeLessThan(31 * 240);
});
