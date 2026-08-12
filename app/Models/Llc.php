<?php

namespace App\Models;

use Database\Factories\LlcFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Llc extends Model
{
    /** @use HasFactory<LlcFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'llcs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'booking_fee_pct' => 'float',
            'suspended_at' => 'datetime',
            'queued_for_retirement_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return MorphMany<LedgerEntry, $this> */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'owner');
    }

    public function isQueuedForRetirement(): bool
    {
        return $this->queued_for_retirement_at !== null;
    }
}
