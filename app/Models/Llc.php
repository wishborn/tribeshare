<?php

namespace App\Models;

use App\Contracts\Retirable;
use App\Models\Concerns\Retires;
use Database\Factories\LlcFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Llc extends Model implements Retirable
{
    /** @use HasFactory<LlcFactory> */
    use HasFactory, HasUuids, Retires, SoftDeletes;

    protected $table = 'llcs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'booking_fee_pct' => 'float',
            ...$this->retirementCasts(),
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

    /** @return HasMany<LlcLeaveQueue, $this> */
    public function leaveQueues(): HasMany
    {
        return $this->hasMany(LlcLeaveQueue::class);
    }
}
