<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->includes($user);
    }

    /**
     * Only a sender may delete their own message.
     *
     * Not an owner, not a manager, not an RCM. A thread that anyone senior
     * can edit is not a record of what was said.
     */
    public function delete(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id && ! $message->bodyWasDeleted();
    }

    /**
     * Downloading an attachment: access follows the conversation, never the
     * URL. Knowing a path is not knowing a secret.
     */
    public function download(User $user, Message $message): bool
    {
        return $message->conversation->includes($user);
    }
}
