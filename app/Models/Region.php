<?php

namespace App\Models;

use App\Contracts\Retirable;
use App\Enums\MessagingScope;
use App\Models\Concerns\Retires;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property MessagingScope|null $messaging_scope
 * @property bool $visible
 */
class Region extends Model implements Retirable
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory, HasUuids, Retires, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'booking_fee_pct' => 'float',
            'messaging_scope' => MessagingScope::class,
            ...$this->retirementCasts(),
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

    /** @return HasMany<RegionDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(RegionDocument::class);
    }

    /** @return HasMany<RegionClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(RegionClaim::class);
    }

    /**
     * Who members here may message, falling back to the platform default.
     *
     * Null means "not chosen", never "no restriction" — a region that has
     * never set one still gets the configured policy.
     */
    public function messagingScope(): MessagingScope
    {
        return $this->messaging_scope
            ?? MessagingScope::from((string) config('tribeshare.messaging.default_scope'));
    }
}
