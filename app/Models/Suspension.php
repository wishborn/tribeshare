<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SuspensionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A bar on a member, globally or within one LLC.
 *
 * @property string $id
 * @property string|null $scopeable_id
 * @property CarbonImmutable|null $lifted_at
 */
class Suspension extends Model
{
    /** @use HasFactory<SuspensionFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /**
     * A suspension with no scope bars the member everywhere.
     */
    public function isGlobal(): bool
    {
        return $this->scopeable_id === null;
    }

    public function isActive(): bool
    {
        return $this->lifted_at === null;
    }

    /** @param  Builder<Suspension>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at');
    }

    /** @param  Builder<Suspension>  $query */
    public function scopeGlobal(Builder $query): void
    {
        $query->whereNull('scopeable_id');
    }
}
