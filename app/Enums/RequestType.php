<?php

namespace App\Enums;

/**
 * What a member is asking for.
 *
 * `JoinPool` appeared under two spellings in the prototype — `joinPool` and
 * `poolJoin` — with the display code handling both. One spelling.
 */
enum RequestType: string
{
    /** Asking for a role. */
    case Hat = 'hat';

    /** Asking to join an asset's pool. */
    case JoinPool = 'join_pool';

    /** Asking to join an LLC. */
    case JoinLlc = 'join_llc';

    /** Submitting an asset for approval. */
    case AddAsset = 'add_asset';

    /** Asking to exceed a booking limit. */
    case CapOverride = 'cap_override';

    /**
     * Whether approving this creates a hat, which therefore exists as
     * pending from the moment the request is raised.
     */
    public function impliesHat(): bool
    {
        return in_array($this, [self::Hat, self::JoinPool, self::JoinLlc, self::AddAsset], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Hat => 'Role request',
            self::JoinPool => 'Pool join request',
            self::JoinLlc => 'LLC join request',
            self::AddAsset => 'Asset submission',
            self::CapOverride => 'Booking cap override',
        };
    }
}
