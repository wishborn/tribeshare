<?php

namespace App\Services\Notifications;

use App\Enums\NotificationKind;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Telling members things.
 *
 * **Preferences are honoured.** In the prototype they were written by one
 * action, read back by one screen, and consulted by nothing — the routine
 * that created every notification never looked at them, so the whole
 * preference page was decorative. Shipping a setting with no effect is worse
 * than either honouring it or removing the screen.
 *
 * Some kinds cannot be muted. Being suspended, being recycled, or being told
 * something about your money is not an interest — it is something you have to
 * know, and a preference screen that offers to hide it is a trap.
 *
 * Every method takes the acting or receiving member explicitly. Two of the
 * prototype's actions worked on "the current user" through an ambient field
 * the API injected, which made them impossible to call from anywhere else.
 */
class NotificationService
{
    public function __construct(private readonly PushService $push) {}

    /**
     * Send one notification, subject to the recipient's preferences.
     *
     * Returns null when the member has asked not to hear about this.
     */
    public function send(
        User $user,
        NotificationKind $kind,
        string $title,
        ?string $body = null,
        ?Model $subject = null,
        ?string $link = null,
        bool $requiresAcknowledgement = false,
    ): ?Notification {
        $preference = $this->preferenceFor($user, $kind);

        if (! $kind->isMandatory() && ! $preference->in_app) {
            return null;
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'link' => $link,
            'requires_acknowledgement' => $requiresAcknowledgement,
        ]);

        // Push is a separate consent from the in-app list: a member may want
        // the record without the interruption.
        if ($kind->isMandatory() || $preference->push) {
            $this->push->send($user, $notification);
        }

        return $notification;
    }

    /**
     * Send the same thing to several members.
     *
     * @param  iterable<int, User>  $users
     * @return array<int, Notification>
     */
    public function sendMany(
        iterable $users,
        NotificationKind $kind,
        string $title,
        ?string $body = null,
        ?Model $subject = null,
        ?string $link = null,
    ): array {
        $sent = [];

        foreach ($users as $user) {
            $notification = $this->send($user, $kind, $title, $body, $subject, $link);

            if ($notification !== null) {
                $sent[] = $notification;
            }
        }

        return $sent;
    }

    /**
     * Dismissing means marking as read.
     *
     * The only thing that ever deletes a notification is clearing the
     * already-read ones, which is a separate, deliberate act.
     */
    public function markRead(Notification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllRead(User $user): int
    {
        return Notification::query()->for($user)->unread()->update(['read_at' => now()]);
    }

    public function acknowledge(Notification $notification): void
    {
        $notification->update([
            'acknowledged_at' => now(),
            'read_at' => $notification->read_at ?? now(),
        ]);
    }

    /**
     * Remove notifications the member has already read.
     *
     * One that demands an acknowledgement survives until it gets one, however
     * often it has been glanced at.
     */
    public function clearRead(User $user): int
    {
        return Notification::query()
            ->for($user)
            ->whereNotNull('read_at')
            ->where(fn ($q) => $q->where('requires_acknowledgement', false)
                ->orWhereNotNull('acknowledged_at'))
            ->delete();
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()->for($user)->unread()->count();
    }

    // --- Preferences -------------------------------------------------------

    public function preferenceFor(User $user, NotificationKind $kind): NotificationPreference
    {
        return $this->preference($user, $kind->preference());
    }

    public function preference(User $user, string $key): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id, 'preference' => $key],
            ['in_app' => true, 'push' => true],
        );
    }

    /**
     * Set a preference. Mandatory kinds have none to set.
     *
     * @param  array<string, array{in_app?: bool, push?: bool}>  $preferences
     * @return array<string, array{in_app: bool, push: bool}>
     */
    public function setPreferences(User $user, array $preferences): array
    {
        DB::transaction(function () use ($user, $preferences): void {
            foreach ($preferences as $key => $channels) {
                if (! in_array($key, NotificationKind::preferenceKeys(), true)) {
                    continue;
                }

                NotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'preference' => $key],
                    [
                        'in_app' => (bool) ($channels['in_app'] ?? true),
                        'push' => (bool) ($channels['push'] ?? true),
                    ],
                );
            }
        });

        return $this->preferencesFor($user);
    }

    /**
     * Every settable preference, defaults included, for the settings screen.
     *
     * @return array<string, array{in_app: bool, push: bool}>
     */
    public function preferencesFor(User $user): array
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('preference');

        $all = [];

        foreach (NotificationKind::preferenceKeys() as $key) {
            $row = $stored->get($key);

            $all[$key] = [
                'in_app' => $row === null ? true : $row->in_app,
                'push' => $row === null ? true : $row->push,
            ];
        }

        return $all;
    }
}
