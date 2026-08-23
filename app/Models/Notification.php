<?php

namespace App\Models;

use App\Enums\NotificationKind;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One thing a member needs to know.
 *
 * Named `tribeshare_notifications` in the database so it does not collide
 * with Laravel's own notifications table, which the framework may yet want.
 *
 * @property NotificationKind $kind
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, HasUuids;

    protected $table = 'tribeshare_notifications';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['requires_acknowledgement' => false];

    protected function casts(): array
    {
        return [
            'kind' => NotificationKind::class,
            'requires_acknowledgement' => 'boolean',
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /** @param  Builder<Notification>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /** @param  Builder<Notification>  $query */
    public function scopeFor(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }
}
