<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | A charge falls due `due_days` after it is raised, and becomes overdue a
    | further `grace_days` later. An overdue charge suspends the member from
    | booking until it is settled.
    |
    | Credit only becomes payable once the income that created it has matured
    | for `payout_maturity_days`.
    |
    | The default carried-balance limit is deliberately NOT here. It is a
    | per-member column whose default has to apply without any application
    | code running, so it lives in the users migration and in the User model's
    | attribute defaults. Putting it here would imply a runtime authority that
    | neither of those consults.
    |
    */

    'billing' => [
        'due_days' => 14,
        'grace_days' => 7,
        'payout_maturity_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fees
    |--------------------------------------------------------------------------
    |
    | LLC and regional fees are charged per person as:
    |
    |     max(feeBase * percentage, flatMinimum)
    |
    | where feeBase is the per-person price floored at `minimum_fee_base_cents`.
    | That floor means a nominally free booking still attracts a fee computed
    | on the floor rather than on zero.
    |
    | Voluntary contributions redirect a share of the asset owner's income to
    | the LLC and region. Their combined percentage may never exceed 100, or a
    | booking would credit more than it debits.
    |
    */

    'fees' => [
        'minimum_fee_base_cents' => 1_00,
        'max_percent' => 10.0,
        'max_total_contribution_percent' => 100.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    |
    | Time is divided into "mesos" of six minutes — 10 an hour, 240 a day.
    | Availability, pricing and allocation rules are all expressed in mesos,
    | though a booking itself is stored as an absolute instant range so it can
    | span midnight.
    |
    */

    'bookings' => [
        'meso_minutes' => 6,
        'mesos_per_day' => 240,
        'default_no_cancel_minutes' => 1440,
        'live_statuses' => ['pending', 'confirmed', 'active'],
    ],

];
