<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'suspended_at' => 'datetime',
            'queued_for_retirement_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Llc, $this> */
    public function llc(): BelongsTo
    {
        return $this->belongsTo(Llc::class);
    }

    /** @return BelongsTo<User, $this> */
    public function mainOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'main_owner_id');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<CollectionItem, $this> */
    public function collectionItems(): HasMany
    {
        return $this->hasMany(CollectionItem::class)->orderBy('position');
    }

    /**
     * Turnaround reserved before and after every booking of this asset, in
     * mesos. A house wants hours; a bike wants minutes.
     *
     * @return array{before: int, after: int}
     */
    public function bookendMesos(): array
    {
        return [
            'before' => max(0, (int) $this->setting('bookend_before_mesos', 0)),
            'after' => max(0, (int) $this->setting('bookend_after_mesos', 0)),
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function poolMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'asset_pool_members')
            ->withTimestamps();
    }

    /**
     * True when this asset, its LLC, or that LLC's region is queued for
     * retirement. Booking is frozen anywhere along that chain.
     */
    public function isFrozenForRetirement(): bool
    {
        if ($this->queued_for_retirement_at !== null) {
            return true;
        }

        $llc = $this->relationLoaded('llc') ? $this->llc : $this->llc()->with('region')->first();

        if ($llc === null) {
            return false;
        }

        return $llc->isQueuedForRetirement() || (bool) $llc->region?->isQueuedForRetirement();
    }

    /**
     * Dot-path lookup into the unstructured settings blob.
     *
     * Settings stay JSON until assets are specified; this keeps the reaching
     * in one place so it is cheap to replace with real columns later.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }
}
