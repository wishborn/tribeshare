<?php

namespace App\Models\Concerns;

use App\Enums\QueueSource;
use Carbon\CarbonImmutable;

/**
 * The retirement lifecycle shared by regions, LLCs and assets.
 *
 * Queuing freezes booking beneath the entity; recycling is the queue actually
 * firing once obligations settle. Both record their PROVENANCE, because
 * restoring a region has to unwind exactly what that region queued — not an
 * LLC retired on its own account, and not one separately condemned.
 *
 * @property CarbonImmutable|null $suspended_at
 * @property CarbonImmutable|null $queued_for_retirement_at
 * @property QueueSource|null $queued_source
 * @property string|null $queued_by
 * @property CarbonImmutable|null $recycled_at
 * @property QueueSource|null $recycled_source
 * @property CarbonImmutable|null $marked_for_deletion_at
 * @property string|null $marked_for_deletion_by
 */
trait Retires
{
    public function isQueuedForRetirement(): bool
    {
        return $this->queued_for_retirement_at !== null;
    }

    public function isRecycled(): bool
    {
        return $this->recycled_at !== null;
    }

    public function queuedSource(): ?QueueSource
    {
        return $this->queued_source;
    }

    public function isMarkedForDeletion(): bool
    {
        return $this->marked_for_deletion_at !== null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Whether this was queued by something above it rather than in its own
     * right — the question a restore asks before unwinding it.
     */
    public function wasQueuedBy(QueueSource $source): bool
    {
        return $this->queued_source === $source || $this->recycled_source === $source;
    }

    /**
     * The casts the lifecycle columns need, merged into each model's own.
     *
     * @return array<string, string>
     */
    protected function retirementCasts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'queued_for_retirement_at' => 'datetime',
            'queued_source' => QueueSource::class,
            'recycled_at' => 'datetime',
            'recycled_source' => QueueSource::class,
            'marked_for_deletion_at' => 'datetime',
        ];
    }
}
