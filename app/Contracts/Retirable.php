<?php

namespace App\Contracts;

use App\Enums\QueueSource;

/**
 * An entity that retires rather than simply disappearing.
 *
 * Regions, LLCs and assets all queue for retirement, cascade to what is
 * beneath them, and recycle once obligations settle. LifecycleService works
 * against this rather than against Model, so the lifecycle columns it reaches
 * for are guaranteed to exist.
 */
interface Retirable
{
    public function isQueuedForRetirement(): bool;

    /**
     * What queued this — itself, or something above it.
     *
     * Recycling carries the provenance forward, so a restore can tell an
     * entity it swept up from one retired on its own account.
     */
    public function queuedSource(): ?QueueSource;

    public function isRecycled(): bool;

    public function isMarkedForDeletion(): bool;

    public function isSuspended(): bool;
}
