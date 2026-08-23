<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Governance is clock-driven: a vote closes when its window expires, and a
// decision applies when its cooling-off has elapsed. Hourly is fine — both
// transitions are stamped with a time, and the sweep is idempotent, so the
// cadence only decides how promptly they are noticed.
Schedule::command('tribeshare:governance-sweep')->hourly()->withoutOverlapping();

// The three grace-period queues. Nothing here is urgent — every transition is
// stamped with a time and the sweep is idempotent — but a member who has
// settled up should not wait long to actually leave.
Schedule::command('tribeshare:offboarding-sweep')->hourly()->withoutOverlapping();
