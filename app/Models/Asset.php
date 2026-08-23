<?php

namespace App\Models;

use App\Contracts\Retirable;
use App\Enums\GroupPriceMode;
use App\Models\Concerns\Retires;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $status
 * @property int $bookend_before_mesos
 * @property int $bookend_after_mesos
 * @property int $max_group_size
 * @property GroupPriceMode $group_price_mode
 * @property float $group_multiplier
 * @property int $group_premium_cents
 * @property float $voluntary_contrib_llc_pct
 * @property float $voluntary_contrib_region_pct
 * @property int $no_cancel_minutes
 * @property Collection<int, AssetSlotType> $slotTypes
 * @property Collection<int, AssetUnitPrice> $unitPrices
 */
class Asset extends Model implements Retirable
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, HasUuids, Retires, SoftDeletes;

    protected $guarded = [];

    /**
     * Defaults for the columns read on the booking path.
     *
     * A database default is applied by the DATABASE and is absent from the
     * model instance that inserted the row, so without these an asset
     * created and used in the same request has a null group price mode and
     * null buffers. Attribute defaults apply on instantiation and, unlike a
     * creating hook, cannot be muted by a seeder.
     *
     * Keep in step with the column defaults in the assets migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'approved',
        'no_cancel_minutes' => 1440,
        'bump_cutoff_minutes' => 1440,
        'min_book_ahead_mesos' => 0,
        'bookend_before_mesos' => 0,
        'bookend_after_mesos' => 0,
        'max_group_size' => 1,
        'group_price_mode' => 'none',
        'group_multiplier' => 1,
        'group_premium_cents' => 0,
        'voluntary_contrib_llc_pct' => 0,
        'voluntary_contrib_region_pct' => 0,
        'allow_give_up' => true,
        'offer_giver_pct' => 0,
        'offer_picker_pct' => 100,
        'pool_closed' => false,
        'pool_approval_by_admins' => true,
        'auto_join_pool' => false,
        'allow_event_hosting' => false,
        'allow_ride_hosting' => false,
        'stated_value_cents' => 0,
        'invisible' => false,
        'subtype' => 'standard',
    ];

    protected function casts(): array
    {
        return [
            // What remains here is presentation and assurance only —
            // description, images, documents, bonding. Everything read on
            // the booking path is now a column.
            'settings' => 'array',
            'draft_settings' => 'array',
            ...$this->retirementCasts(),
            'approved_at' => 'datetime',
            'verified_at' => 'datetime',
            'group_price_mode' => GroupPriceMode::class,
            'group_multiplier' => 'float',
            'voluntary_contrib_llc_pct' => 'float',
            'voluntary_contrib_region_pct' => 'float',
            'offer_giver_pct' => 'float',
            'offer_picker_pct' => 'float',
            'allow_give_up' => 'boolean',
            'pool_closed' => 'boolean',
            'pool_approval_by_admins' => 'boolean',
            'auto_join_pool' => 'boolean',
            'allow_event_hosting' => 'boolean',
            'allow_ride_hosting' => 'boolean',
            'invisible' => 'boolean',
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

    /** @return HasMany<AssetSlotType, $this> */
    public function slotTypes(): HasMany
    {
        return $this->hasMany(AssetSlotType::class)->orderBy('position');
    }

    /** @return HasMany<AssetUnitPrice, $this> */
    public function unitPrices(): HasMany
    {
        return $this->hasMany(AssetUnitPrice::class)->orderBy('position');
    }

    /** @return HasMany<Calendar, $this> */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'schedulable_id')
            ->where('schedulable_type', $this->getMorphClass());
    }

    /** @return HasMany<CalendarTemplate, $this> */
    public function calendarTemplates(): HasMany
    {
        return $this->hasMany(CalendarTemplate::class);
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
            'before' => max(0, $this->bookend_before_mesos),
            'after' => max(0, $this->bookend_after_mesos),
        ];
    }

    /**
     * The base price for a slot, in cents. Null when the asset does not
     * offer that duration.
     */
    public function priceForSlot(string $key): ?int
    {
        $slot = $this->slotTypes->firstWhere('key', $key);

        return $slot !== null && $slot->enabled ? $slot->price_cents : null;
    }

    /**
     * Whether this asset meters usage, and so needs a report after each
     * booking completes.
     */
    public function isMetered(): bool
    {
        return $this->unitPrices()->exists();
    }

    /**
     * What a set of reported quantities costs.
     *
     * @param  array<string, int|float>  $quantities  unit => quantity
     */
    public function unitChargeFor(array $quantities): int
    {
        return $this->unitPrices->reduce(
            fn (int $total, AssetUnitPrice $price) => $total + $price->chargeFor($quantities[$price->unit] ?? 0),
            0,
        );
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
