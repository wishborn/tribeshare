<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\MessagingScopeResolver;

/**
 * Authority over a thread.
 *
 * Every rule here was enforced in the messages page and nowhere else, which
 * made all of them advisory: a direct call bypassed the lot.
 */
class ConversationPolicy
{
    public function __construct(private readonly MessagingScopeResolver $scope) {}

    /**
     * Reading a thread means being in it. Not even an RCM reads a private
     * conversation they are not part of — the platform's stewards are not
     * exempt from the confidence members place in a direct message.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->includes($user);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->includes($user);
    }

    /**
     * Adding people to an existing thread, rather than starting a new one.
     */
    public function addMembers(User $user, Conversation $conversation): bool
    {
        return ! $conversation->is_direct && $conversation->includes($user);
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        return $conversation->includes($user);
    }

    /**
     * Archiving is per member, so anyone in the thread may archive their own
     * view of it. Nobody else's changes.
     */
    public function archive(User $user, Conversation $conversation): bool
    {
        return $conversation->includes($user);
    }

    /**
     * Whether this member may open a conversation with another at all.
     *
     * Checked here as well as in the service, so the interface can grey out
     * what the server would refuse rather than offering it and failing.
     */
    public function messageMember(User $user, User $recipient): bool
    {
        return $this->scope->mayMessage($user, $recipient);
    }
}
