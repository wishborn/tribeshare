<?php

namespace App\Services\Calendar;

use App\Models\Asset;
use App\Models\Calendar;
use App\Models\CalendarRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Availability, per-slice pricing and priority.
 *
 * Two rules govern everything here:
 *
 *  - **A month must be published to be bookable.**
 *  - **Rules opt slices in.** A meso with no rule is closed, so an empty or
 *    unruled month sells nothing by accident. That also makes coverage mean
 *    what it claims.
 */
class CalendarService
{
    public function for(Asset $asset, string $month): Calendar
    {
        return Calendar::firstOrCreate([
            'schedulable_type' => $asset->getMorphClass(),
            'schedulable_id' => $asset->getKey(),
            'month' => $month,
        ]);
    }

    /**
     * Resolve a booking's whole range — which may span days and months.
     *
     * The multiplier returned is the mean across every meso requested,
     * weighted naturally by how many mesos fall under each rule. A booking
     * crossing midnight therefore averages across both days, which the
     * prototype's single-day model could not express.
     */
    public function resolve(Asset $asset, CarbonInterface $startsAt, CarbonInterface $endsAt): SliceResolution
    {
        $mesoMinutes = (int) config('tribeshare.bookings.meso_minutes');
        $totalMesos = (int) round($startsAt->diffInMinutes($endsAt) / $mesoMinutes);

        if ($totalMesos <= 0) {
            return SliceResolution::closed(0, 'The range is empty.');
        }

        $calendars = $this->publishedCalendarsSpanning($asset, $startsAt, $endsAt);

        // Every month the booking touches must be published — a stay that
        // runs into an unpublished month is not bookable for its whole
        // length.
        foreach (Calendar::monthsSpanning($startsAt, $endsAt) as $month) {
            if (! isset($calendars[$month])) {
                return SliceResolution::closed($totalMesos, "The calendar for {$month} is not published.");
            }
        }

        $covered = 0;
        $multiplierTotal = 0.0;
        $reasons = [];

        foreach ($this->mesosIn($startsAt, $totalMesos, $mesoMinutes) as [$month, $day, $meso]) {
            $rule = $this->ruleFor($calendars[$month] ?? null, $day, $meso);

            if ($rule === null) {
                $reasons['unruled'] = 'Part of this range has no calendar rule.';

                continue;
            }

            if (! $rule->bookable) {
                $reasons['closed'] = 'Part of this range is marked unbookable.';

                continue;
            }

            $covered++;
            $multiplierTotal += $rule->price_multiplier_pct;
        }

        return new SliceResolution(
            bookable: $covered === $totalMesos,
            // 100 means normal price. An uncovered range never prices, so
            // falling back to 100 here is inert rather than meaningful.
            multiplierPct: $covered > 0 ? $multiplierTotal / $covered : 100.0,
            mesosCovered: $covered,
            mesosRequested: $totalMesos,
            reasons: array_values($reasons),
        );
    }

    /**
     * Whether a member is barred from a slice, or protected within it.
     *
     * Hats gate and lists order — so this reports only the two exceptions
     * that genuinely override rank.
     *
     * @return array{barred: bool, unbumpable: bool, position: int|null}
     */
    public function standingFor(User $user, Asset $asset, CarbonInterface $startsAt, CarbonInterface $endsAt): array
    {
        $mesoMinutes = (int) config('tribeshare.bookings.meso_minutes');
        $totalMesos = (int) round($startsAt->diffInMinutes($endsAt) / $mesoMinutes);
        $calendars = $this->publishedCalendarsSpanning($asset, $startsAt, $endsAt);

        $barred = false;
        $unbumpable = false;
        $position = null;

        foreach ($this->mesosIn($startsAt, $totalMesos, $mesoMinutes) as [$month, $day, $meso]) {
            $rule = $this->ruleFor($calendars[$month] ?? null, $day, $meso);
            $entry = $rule?->priorities->firstWhere('user_id', $user->id);

            if ($entry === null) {
                continue;
            }

            // Barred anywhere in the range bars the whole booking.
            $barred = $barred || $entry->cannot_book;
            $unbumpable = $unbumpable || $entry->unbumpable;
            $position = $position === null ? $entry->position : min($position, $entry->position);
        }

        return ['barred' => $barred, 'unbumpable' => $unbumpable, 'position' => $position];
    }

    /**
     * Promote a month's draft rules to live, and publish it.
     *
     * Explicit, not a toggle — the prototype's publish flipped whichever way
     * the month happened to be pointing.
     */
    public function publish(Calendar $calendar, ?User $by = null): void
    {
        DB::transaction(function () use ($calendar, $by): void {
            if ($calendar->hasDraft()) {
                $calendar->publishedRules()->delete();
                $calendar->draftRules()->update(['draft' => false]);
            }

            $calendar->update([
                'published_at' => now(),
                'published_by' => $by?->id,
            ]);
        });
    }

    public function unpublish(Calendar $calendar): void
    {
        $calendar->update(['published_at' => null, 'published_by' => null]);
    }

    public function discardDraft(Calendar $calendar): void
    {
        $calendar->draftRules()->delete();
    }

    /**
     * How much of a month's future is ruled.
     *
     * Past mesos are excluded rather than counted as gaps — a month half
     * gone is not half unfinished.
     *
     * @return array{covered: int, total: int, pct: int}
     */
    public function coverage(Calendar $calendar, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $mesosPerDay = (int) config('tribeshare.bookings.mesos_per_day');
        $monthStart = CarbonImmutable::createFromFormat('Y-m-d', $calendar->month.'-01');
        $daysInMonth = $monthStart->daysInMonth;

        $rules = $calendar->publishedRules()->get();

        $total = 0;
        $covered = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $monthStart->setDay($day);

            for ($meso = 0; $meso < $mesosPerDay; $meso++) {
                if ($date->addMinutes($meso * (int) config('tribeshare.bookings.meso_minutes'))->lessThan($now)) {
                    continue;
                }

                $total++;

                if ($rules->contains(fn (CalendarRule $rule) => $rule->day === $day && $rule->covers($meso))) {
                    $covered++;
                }
            }
        }

        return [
            'covered' => $covered,
            'total' => $total,
            'pct' => $total > 0 ? (int) round($covered / $total * 100) : 0,
        ];
    }

    /**
     * @return array<string, Calendar>
     */
    private function publishedCalendarsSpanning(Asset $asset, CarbonInterface $from, CarbonInterface $until): array
    {
        return Calendar::query()
            ->where('schedulable_type', $asset->getMorphClass())
            ->where('schedulable_id', $asset->getKey())
            ->whereIn('month', Calendar::monthsSpanning($from, $until))
            ->published()
            ->with(['publishedRules.priorities'])
            ->get()
            ->keyBy('month')
            ->all();
    }

    /**
     * Walk a range meso by meso, yielding the month, day and meso of each.
     *
     * @return \Generator<int, array{0: string, 1: int, 2: int}>
     */
    private function mesosIn(CarbonInterface $startsAt, int $totalMesos, int $mesoMinutes): \Generator
    {
        for ($i = 0; $i < $totalMesos; $i++) {
            $at = $startsAt->copy()->addMinutes($i * $mesoMinutes);

            yield [
                $at->format('Y-m'),
                (int) $at->format('j'),
                (int) floor(($at->hour * 60 + $at->minute) / $mesoMinutes),
            ];
        }
    }

    private function ruleFor(?Calendar $calendar, int $day, int $meso): ?CalendarRule
    {
        if ($calendar === null) {
            return null;
        }

        return $calendar->publishedRules
            ->first(fn (CalendarRule $rule) => $rule->day === $day && $rule->covers($meso));
    }
}
