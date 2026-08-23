<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use Database\Factories\MemberRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Something a member is asking for.
 *
 * Named `MemberRequest` rather than `Request` so it never reads ambiguously
 * next to an HTTP request.
 *
 * @property RequestType $type
 * @property RequestStatus $status
 * @property array<string, mixed>|null $payload
 */
class MemberRequest extends Model
{
    /** @use HasFactory<MemberRequestFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'type' => RequestType::class,
            'status' => RequestStatus::class,
            'payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The hat this request implies, inactive until approval.
     *
     * @return BelongsTo<Hat, $this>
     */
    public function pendingHat(): BelongsTo
    {
        return $this->belongsTo(Hat::class, 'pending_hat_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @param  Builder<MemberRequest>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', RequestStatus::Pending);
    }
}
