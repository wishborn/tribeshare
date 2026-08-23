<?php

namespace App\Enums;

/**
 * Why an entity is queued for retirement.
 *
 * Restoring has to unwind exactly what a retirement queued and nothing else,
 * so the cascade records its own provenance. Without this a restored region
 * would sweep up LLCs that were retired on their own account.
 */
enum QueueSource: string
{
    /** Queued in its own right. */
    case Direct = 'direct';

    /** Queued because the region above it was. */
    case Region = 'region';

    /** Queued because the LLC above it was. */
    case Llc = 'llc';

    public function isCascaded(): bool
    {
        return $this !== self::Direct;
    }
}
