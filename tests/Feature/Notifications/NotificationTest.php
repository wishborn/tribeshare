<?php

use App\Enums\HatType;
use App\Enums\NotificationKind;
use App\Models\Llc;
use App\Models\Notification;
use App\Models\PageSeenCount;
use App\Models\Region;
use App\Models\User;
use App\Services\Notifications\BadgeService;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\PushService;
use App\Services\Permissions\HatService;

beforeEach(function () {
    $this->notifications = app(NotificationService::class);
    $this->badges = app(BadgeService::class);
    $this->user = User::factory()->create();
});

// --- Preferences are honoured ----------------------------------------------

it('sends what the member has asked to hear about', function () {
    $notification = $this->notifications->send(
        $this->user,
        NotificationKind::Governance,
        'A proposal is open',
    );

    expect($notification)->not->toBeNull()
        ->and($notification->kind)->toBe(NotificationKind::Governance);
});

it('withholds what the member has switched off', function () {
    $this->notifications->setPreferences($this->user, ['governance' => ['in_app' => false, 'push' => false]]);

    // In the prototype preferences were written by one action, read back by
    // one screen, and consulted by nothing — the routine that created every
    // notification never looked at them.
    expect($this->notifications->send($this->user, NotificationKind::Governance, 'A proposal is open'))
        ->toBeNull()
        ->and(Notification::count())->toBe(0);
});

it('mutes every kind that shares a preference', function () {
    $this->notifications->setPreferences($this->user, ['bookings' => ['in_app' => false, 'push' => true]]);

    // Muting bookings means bumps and offer-ups too — they are the same
    // interest under three names.
    expect($this->notifications->send($this->user, NotificationKind::Bump, 'Bumped'))->toBeNull()
        ->and($this->notifications->send($this->user, NotificationKind::OfferUp, 'Offered'))->toBeNull();
});

it('sends what a member may not switch off however they try', function () {
    $this->notifications->setPreferences($this->user, [
        'billing' => ['in_app' => false, 'push' => false],
        'account' => ['in_app' => false, 'push' => false],
    ]);

    // Being suspended, or told something about your money, is not an
    // interest. A preference screen that offers to hide it is a trap.
    expect($this->notifications->send($this->user, NotificationKind::Billing, 'Overdue'))->not->toBeNull()
        ->and($this->notifications->send($this->user, NotificationKind::Suspended, 'Suspended'))->not->toBeNull();
});

it('offers no preference for the kinds nobody may mute', function () {
    $keys = NotificationKind::preferenceKeys();

    expect($keys)->not->toContain('billing')
        ->and($keys)->not->toContain('account')
        ->and($keys)->toContain('governance');
});

it('treats a preference nobody has set as on', function () {
    expect($this->notifications->preferencesFor($this->user)['messages'])
        ->toBe(['in_app' => true, 'push' => true]);
});

it('ignores an attempt to set a preference that does not exist', function () {
    $set = $this->notifications->setPreferences($this->user, ['nonsense' => ['in_app' => false]]);

    expect($set)->not->toHaveKey('nonsense');
});

it('keeps the in-app record when only push is switched off', function () {
    $this->notifications->setPreferences($this->user, ['messages' => ['in_app' => true, 'push' => false]]);

    // A member may want the record without the interruption.
    expect($this->notifications->send($this->user, NotificationKind::Message, 'A message'))->not->toBeNull();
});

// --- Reading, dismissing, clearing ------------------------------------------

it('dismisses by marking read rather than deleting', function () {
    $notification = Notification::factory()->for($this->user)->create();

    $this->notifications->markRead($notification);

    expect($notification->refresh()->isRead())->toBeTrue()
        ->and(Notification::count())->toBe(1);
});

it('clears only what has been read', function () {
    Notification::factory()->for($this->user)->read()->count(2)->create();
    Notification::factory()->for($this->user)->create();

    $cleared = $this->notifications->clearRead($this->user);

    expect($cleared)->toBe(2)
        ->and(Notification::count())->toBe(1);
});

it('keeps an unacknowledged notification however often it is read', function () {
    $notification = Notification::factory()->for($this->user)->read()->create([
        'requires_acknowledgement' => true,
    ]);

    expect($this->notifications->clearRead($this->user))->toBe(0);

    $this->notifications->acknowledge($notification);

    expect($this->notifications->clearRead($this->user))->toBe(1);
});

it('marks everything read at once', function () {
    Notification::factory()->for($this->user)->count(3)->create();

    expect($this->notifications->markAllRead($this->user))->toBe(3)
        ->and($this->notifications->unreadCount($this->user))->toBe(0);
});

// --- Push --------------------------------------------------------------------

it('skips push while the keys are absent', function () {
    $push = app(PushService::class);

    // Push is skipped rather than throwing, so a half-configured deployment
    // still delivers the in-app record — which is the one that matters.
    expect($push->isConfigured())->toBeFalse()
        ->and($push->send($this->user, Notification::factory()->for($this->user)->create()))->toBe(0);
});

it('refreshes a browser subscription rather than duplicating it', function () {
    $push = app(PushService::class);

    $push->subscribe($this->user, 'https://push.example/a', 'key-1', 'auth-1');
    $push->subscribe($this->user, 'https://push.example/b', 'key-1', 'auth-2');

    expect($this->user->pushSubscriptions()->count())->toBe(1)
        ->and($this->user->pushSubscriptions()->sole()->endpoint)->toBe('https://push.example/b');
});

// --- Badges -------------------------------------------------------------------

it('shows a badge for what has arrived since the member last looked', function () {
    Notification::factory()->for($this->user)->count(2)->create();

    expect($this->badges->forMember($this->user)['notifications'])
        ->toBe(['count' => 2, 'badge' => true]);

    $this->badges->markSeen($this->user, 'notifications');

    expect($this->badges->forMember($this->user)['notifications']['badge'])->toBeFalse();

    Notification::factory()->for($this->user)->create();

    // The count exceeds what was seen, so the badge returns.
    expect($this->badges->forMember($this->user)['notifications']['badge'])->toBeTrue();
});

it('never fades the pages configured not to', function () {
    $region = Region::factory()->create();
    $llc = Llc::factory()->for($region)->create();
    app(HatService::class)->grant($this->user, HatType::LlcMember, $llc);

    config()->set('tribeshare.notifications.never_fades', ['notifications']);

    Notification::factory()->for($this->user)->create();
    $this->badges->markSeen($this->user, 'notifications');

    // Looking at it does not make it stop mattering.
    expect($this->badges->forMember($this->user)['notifications']['badge'])->toBeTrue();
});

it('records nothing for a page excluded from seen-tracking', function () {
    config()->set('tribeshare.notifications.excluded_from_seen', ['notifications']);

    Notification::factory()->for($this->user)->create();
    $this->badges->markSeen($this->user, 'notifications');

    expect($this->user->fresh()->id)->not->toBeNull()
        ->and(PageSeenCount::where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and($this->badges->forMember($this->user)['notifications']['badge'])->toBeTrue();
});

it('shows no badge when there is nothing to show', function () {
    expect($this->badges->forMember($this->user)['notifications'])
        ->toBe(['count' => 0, 'badge' => false]);
});
