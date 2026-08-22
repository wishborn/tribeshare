<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A field a decision froze, so it cannot be quietly reversed by an owner.
 */
class GovernanceLock extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * Whether a decision has frozen this field on this entity.
     *
     * The prototype recorded locks and never consulted them, so a lock was
     * decoration. Checking it here is what makes "a decision cannot be
     * quietly reversed by an owner" true rather than aspirational: the
     * ordinary edit paths ask, and only a repeal lifts it.
     */
    public static function locks(Model $entity, string $field): bool
    {
        return static::query()
            ->where('lockable_type', $entity->getMorphClass())
            ->where('lockable_id', $entity->getKey())
            ->where('field', $field)
            ->exists();
    }
}
