<?php

namespace App\Models;

use App\Enums\OffboardingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member queued for removal, waiting on their obligations.
 *
 * @property OffboardingStatus $status
 */
class MemberRemovalQueue extends Model
{
    use HasUuids;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'queued'];

    protected function casts(): array
    {
        return [
            'status' => OffboardingStatus::class,
            'queued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'fired_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function queuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'queued_by');
    }

    /** @param  Builder<MemberRemovalQueue>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', OffboardingStatus::Queued);
    }
}
