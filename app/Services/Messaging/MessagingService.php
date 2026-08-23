<?php

namespace App\Services\Messaging;

use App\Enums\NotificationKind;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Conversations and the messages in them.
 *
 * Three rules the prototype left to the interface are enforced here: the
 * sender must be a participant, messaging scope binds on create and on send,
 * and only a sender may delete their own message.
 */
class MessagingService
{
    public function __construct(
        private readonly MessagingScopeResolver $scope,
        private readonly AttachmentService $attachments,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Open a direct conversation, or return the one that already exists.
     *
     * Deduplicated on a sorted key, so the same pair produces the same thread
     * whichever of them starts it.
     */
    public function openDirect(User $sender, User $recipient): Conversation
    {
        $this->assertMayMessage($sender, $recipient);

        $key = Conversation::directKeyFor([$sender->id, $recipient->id]);

        $existing = Conversation::query()->where('direct_key', $key)->first();

        if ($existing !== null) {
            // Either member may have left; re-opening puts them back rather
            // than stranding the thread.
            $this->rejoin($existing, $sender);
            $this->rejoin($existing, $recipient);

            return $existing;
        }

        return DB::transaction(function () use ($sender, $recipient, $key): Conversation {
            $conversation = Conversation::create([
                'is_direct' => true,
                'direct_key' => $key,
                'created_by' => $sender->id,
            ]);

            $this->addParticipant($conversation, $sender);
            $this->addParticipant($conversation, $recipient);

            return $conversation;
        });
    }

    /**
     * Start a group conversation.
     *
     * @param  array<int, User>  $members
     */
    public function openGroup(User $creator, array $members, ?string $name = null, ?Model $scopeable = null): Conversation
    {
        foreach ($members as $member) {
            $this->assertMayMessage($creator, $member);
        }

        return DB::transaction(function () use ($creator, $members, $name, $scopeable): Conversation {
            $conversation = Conversation::create([
                'name' => $name,
                'created_by' => $creator->id,
                'scopeable_type' => $scopeable?->getMorphClass(),
                'scopeable_id' => $scopeable?->getKey(),
            ]);

            $this->addParticipant($conversation, $creator);

            foreach ($members as $member) {
                $this->addParticipant($conversation, $member);
            }

            return $conversation;
        });
    }

    /**
     * Post a message.
     *
     * @param  array<int, UploadedFile>  $files
     *
     * @throws RuntimeException when the sender is not in the thread, or scope
     *                          has since been tightened against them
     */
    public function send(Conversation $conversation, User $sender, ?string $body, array $files = []): Message
    {
        // The prototype keyed sending on a conversation id alone, so any
        // member could post into any conversation.
        if (! $conversation->includes($sender)) {
            throw new RuntimeException('You are not part of that conversation.');
        }

        if ($body === null && $files === []) {
            throw new RuntimeException('A message needs a body or an attachment.');
        }

        // Scope binds on send as well as on create, because a region can
        // tighten its policy after a thread exists.
        foreach ($this->otherMembers($conversation, $sender) as $other) {
            $this->assertMayMessage($sender, $other);
        }

        $message = DB::transaction(function () use ($conversation, $sender, $body, $files): Message {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            foreach ($files as $file) {
                $this->attachments->attach($message, $file);
            }

            // Seeded with the sender: you have read what you just wrote.
            $this->markRead($message, $sender);

            $conversation->update([
                'last_message_at' => $message->created_at,
                'preview' => $this->previewFor($body, count($files)),
            ]);

            return $message;
        });

        $this->announce($conversation, $message, $sender);

        return $message;
    }

    public function markRead(Message $message, User $user): void
    {
        MessageRead::query()->updateOrInsert(
            ['message_id' => $message->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }

    /**
     * Mark a whole thread read for one member.
     */
    public function markConversationRead(Conversation $conversation, User $user): int
    {
        $unread = $conversation->messages()
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        foreach ($unread as $message) {
            $this->markRead($message, $user);
        }

        return $unread->count();
    }

    public function unreadCount(User $user): int
    {
        return Message::query()
            ->where('sender_id', '!=', $user->id)
            ->whereHas('conversation.participants', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereNull('left_at'))
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }

    /**
     * Delete a message's body, keeping its place in the thread.
     *
     * @throws RuntimeException when someone other than the sender tries
     */
    public function deleteMessage(Message $message, User $actor): void
    {
        if ($message->sender_id !== $actor->id) {
            throw new RuntimeException('Only the sender may delete a message.');
        }

        DB::transaction(function () use ($message): void {
            $message->attachments->each(fn ($attachment) => $this->attachments->remove($attachment));

            $message->update(['body' => null, 'body_deleted_at' => now()]);
        });
    }

    // --- Membership --------------------------------------------------------

    /**
     * Add members to an existing thread.
     *
     * Merges rather than replaces, so a late arrival joins the conversation
     * instead of starting a new one.
     *
     * @param  array<int, User>  $members
     */
    public function addMembers(Conversation $conversation, User $actor, array $members): void
    {
        if (! $conversation->includes($actor)) {
            throw new RuntimeException('You are not part of that conversation.');
        }

        if ($conversation->is_direct) {
            throw new RuntimeException('A direct conversation is between two people.');
        }

        foreach ($members as $member) {
            $this->assertMayMessage($actor, $member);
            $this->addParticipant($conversation, $member);
        }
    }

    /**
     * Leave a thread.
     *
     * When the LAST participant leaves, the conversation is archived for
     * everyone who was ever in it, so it disappears rather than lingering
     * empty.
     */
    public function leave(Conversation $conversation, User $user): void
    {
        DB::transaction(function () use ($conversation, $user): void {
            $conversation->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->update(['left_at' => now()]);

            $remaining = $conversation->participants()->whereNull('left_at')->count();

            if ($remaining === 0) {
                $conversation->participants()->update(['archived_at' => now()]);
                $conversation->delete();
            }
        });
    }

    /**
     * Archive a thread for one member.
     *
     * Explicit verbs, not a toggle — a caller should not have to know the
     * current state to predict the outcome.
     */
    public function archive(Conversation $conversation, User $user): void
    {
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['archived_at' => now()]);
    }

    public function unarchive(Conversation $conversation, User $user): void
    {
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['archived_at' => null]);
    }

    // --- Internals ---------------------------------------------------------

    private function addParticipant(Conversation $conversation, User $user): ConversationParticipant
    {
        return ConversationParticipant::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['joined_at' => now(), 'left_at' => null],
        );
    }

    private function rejoin(Conversation $conversation, User $user): void
    {
        $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('left_at')
            ->update(['left_at' => null, 'joined_at' => now(), 'archived_at' => null]);
    }

    /**
     * @return Collection<int, User>
     */
    private function otherMembers(Conversation $conversation, User $except)
    {
        return $conversation->members()->whereKeyNot($except->id)->get();
    }

    private function assertMayMessage(User $sender, User $recipient): void
    {
        if (! $this->scope->mayMessage($sender, $recipient)) {
            throw new RuntimeException($this->scope->refusalReason($sender, $recipient));
        }
    }

    private function previewFor(?string $body, int $attachmentCount): string
    {
        $length = (int) config('tribeshare.messaging.preview_length', 80);

        if ($body !== null && trim($body) !== '') {
            return mb_substr($body, 0, $length);
        }

        return $attachmentCount === 1 ? 'Sent an attachment' : "Sent {$attachmentCount} attachments";
    }

    /**
     * Tell everyone else in the thread, subject to their preferences.
     */
    private function announce(Conversation $conversation, Message $message, User $sender): void
    {
        $title = $conversation->name ?? $sender->name;

        $this->notifications->sendMany(
            $this->otherMembers($conversation, $sender),
            NotificationKind::Message,
            $title,
            $conversation->preview,
            subject: $conversation,
        );
    }
}
