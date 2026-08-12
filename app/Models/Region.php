<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'booking_fee_pct' => 'float',
            'suspended_at' => 'datetime',
            'queued_for_retirement_at' => 'datetime',
        ];
    }

    /** @return HasMany<Llc, $this> */
    public function llcs(): HasMany
    {
        return $this->hasMany(Llc::class);
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
