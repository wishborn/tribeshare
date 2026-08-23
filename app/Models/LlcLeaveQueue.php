<?php

namespace App\Models;

use App\Enums\OffboardingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's own request to leave an LLC.
 *
 * Self-service, but not instant: it waits on the same obligations any other
 * departure does, and may be cancelled while it is still queued.
 *
 * @property OffboardingStatus $status
 */
class LlcLeaveQueue extends Model
{
    use HasUuids;

    protected $table = 'llc_leave_queues';

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

    /** @return BelongsTo<Llc, $this> */
    public function llc(): BelongsTo
    {
        return $this->belongsTo(Llc::class);
    }

    /** @param  Builder<LlcLeaveQueue>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', OffboardingStatus::Queued);
    }
}
