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

    /*
    |--------------------------------------------------------------------------
    | Messaging
    |--------------------------------------------------------------------------
    |
    | Who a member may start a conversation with is set PER REGION; this is
    | the default a region inherits until it chooses otherwise. The prototype
    | held it platform-wide and never enforced it anywhere but the page.
    |
    | Attachments are real files with a size ceiling and an allow-list. The
    | prototype recorded them without ever defining what they were.
    |
    */

    'messaging' => [
        'default_scope' => 'llc_only',
        'preview_length' => 80,

        'attachments' => [
            'disk' => 'local',
            'max_bytes' => 10 * 1024 * 1024,
            'max_per_message' => 10,
            'allowed_mime_types' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic',
                'application/pdf',
                'text/plain', 'text/csv',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Badge pages, and the two that behave unusually: bookings never fades,
    | and some pages are excluded from seen-tracking entirely.
    |
    | Web push needs a VAPID key pair. Generate one with
    | `php artisan tribeshare:push-keys` and put it in the environment; push
    | is simply skipped while the keys are absent, so nothing breaks locally.
    |
    */

    'notifications' => [
        'badge_pages' => [
            'bookings', 'messages', 'requests', 'governance', 'notifications', 'billing',
        ],
        'never_fades' => ['bookings'],
        'excluded_from_seen' => ['billing'],

        'push' => [
            'enabled' => env('PUSH_ENABLED', true),
            'subject' => env('PUSH_SUBJECT', 'mailto:support@tribeshare.test'),
            'public_key' => env('VAPID_PUBLIC_KEY'),
            'private_key' => env('VAPID_PRIVATE_KEY'),
            'ttl' => 2_592_000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Offboarding
    |--------------------------------------------------------------------------
    |
    | Nothing is removed immediately. Retiring a region, queueing a member for
    | recycle and quitting an LLC all queue and fire automatically once the
    | member's or entity's obligations settle.
    |
    | An obligation is an open booking OR an unsettled ledger — money owed,
    | credit stranded, or a payout still pending. The prototype counted only
    | bookings, so a member could leave owing money or forfeit a balance.
    |
    */

    'offboarding' => [
        'obligations' => [
            'open_bookings' => true,
            'outstanding_charges' => true,
            'unsettled_credit' => true,
            'pending_payouts' => true,
        ],
    ],

];
