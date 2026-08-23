<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser a member wants pushed to.
 */
class PushSubscription extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['auth_token', 'public_key'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<PushSubscription>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('expired_at');
    }
}
