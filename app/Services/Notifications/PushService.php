<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Web push, actually delivered.
 *
 * The prototype's push module was an explicitly-marked placeholder — it
 * documented where to substitute a real FCM or APNs call, registered a channel
 * per conversation, and set aside a `pushDispatched` flag that nothing ever
 * wrote. Push had never worked, so this is built rather than ported.
 *
 * Absent VAPID keys, push is skipped silently. That keeps local development
 * and the test suite working without ceremony, and a deployment that wants
 * push configures the keys.
 */
class PushService
{
    /**
     * Register a browser to be pushed to.
     *
     * Keyed on the subscription's public key, so re-subscribing the same
     * browser refreshes it rather than accumulating dead rows.
     */
    public function subscribe(
        User $user,
        string $endpoint,
        string $publicKey,
        string $authToken,
        ?string $userAgent = null,
        string $contentEncoding = 'aesgcm',
    ): PushSubscription {
        return PushSubscription::updateOrCreate(
            ['user_id' => $user->id, 'public_key' => $publicKey],
            [
                'endpoint' => $endpoint,
                'auth_token' => $authToken,
                'content_encoding' => $contentEncoding,
                'user_agent' => $userAgent,
                'expired_at' => null,
            ],
        );
    }

    public function unsubscribe(User $user, string $publicKey): void
    {
        PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('public_key', $publicKey)
            ->delete();
    }

    /**
     * Push a notification to every live browser the member has registered.
     *
     * @return int how many were delivered
     */
    public function send(User $user, Notification $notification): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $subscriptions = PushSubscription::query()
            ->where('user_id', $user->id)
            ->live()
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $payload = json_encode([
            'title' => $notification->title,
            'body' => $notification->body,
            'kind' => $notification->kind->value,
            'link' => $notification->link,
            'id' => $notification->id,
        ]) ?: '{}';

        return $this->dispatch($subscriptions, $payload);
    }

    /**
     * Whether push can run at all.
     *
     * Checked rather than assumed, because a half-configured deployment
     * should skip push, not throw on every notification.
     */
    public function isConfigured(): bool
    {
        return (bool) config('tribeshare.notifications.push.enabled')
            && filled(config('tribeshare.notifications.push.public_key'))
            && filled(config('tribeshare.notifications.push.private_key'));
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    private function dispatch($subscriptions, string $payload): int
    {
        try {
            $push = new WebPush(['VAPID' => [
                'subject' => (string) config('tribeshare.notifications.push.subject'),
                'publicKey' => (string) config('tribeshare.notifications.push.public_key'),
                'privateKey' => (string) config('tribeshare.notifications.push.private_key'),
            ]]);

            $push->setDefaultOptions(['TTL' => (int) config('tribeshare.notifications.push.ttl')]);
        } catch (Throwable $e) {
            // Misconfigured keys must not take a notification down with them:
            // the in-app record is the one that matters.
            Log::warning('Push unavailable: '.$e->getMessage());

            return 0;
        }

        $byEndpoint = [];

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->endpoint] = $subscription;

            $push->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                $payload,
            );
        }

        $delivered = 0;

        foreach ($push->flush() as $report) {
            $subscription = $byEndpoint[$report->getRequest()->getUri()->__toString()] ?? null;

            if ($report->isSuccess()) {
                $delivered++;
                $subscription?->forceFill(['last_used_at' => now()])->save();

                continue;
            }

            // A browser that has retired a subscription says so, and there is
            // no point pushing to it again. Marked rather than deleted, so a
            // member asking why notifications stopped has an answer.
            if ($report->isSubscriptionExpired()) {
                $subscription?->forceFill(['expired_at' => now()])->save();
            }
        }

        return $delivered;
    }
}
