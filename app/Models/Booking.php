<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property BookingStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $occupies_from
 * @property CarbonImmutable $occupies_until
 * @property int $bookend_before_mesos
 * @property int $bookend_after_mesos
 * @property string|null $collection_item_id
 * @property int $duration_mesos
 * @property int $priority
 * @property int $per_person_cents
 * @property int $llc_fee_cents
 * @property int $region_fee_cents
 * @property int $total_cents
 * @property bool $bullied
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'occupies_from' => 'datetime',
            'occupies_until' => 'datetime',
            'bumped_at' => 'datetime',
            'bullied' => 'boolean',
            'price_multiplier_pct' => 'float',
            'llc_fee_pct' => 'float',
            'region_fee_pct' => 'float',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Llc, $this> */
    public function llc(): BelongsTo
    {
        return $this->belongsTo(Llc::class);
    }

    /** @return BelongsTo<BookingGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(BookingGroup::class, 'booking_group_id');
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return HasOne<BookingOffer, $this> */
    public function offer(): HasOne
    {
        return $this->hasOne(BookingOffer::class)->latestOfMany();
    }

    /** @return HasOne<BookingUnitReport, $this> */
    public function unitReport(): HasOne
    {
        return $this->hasOne(BookingUnitReport::class);
    }

    /** @return HasOne<BookingEvent, $this> */
    public function event(): HasOne
    {
        return $this->hasOne(BookingEvent::class);
    }

    /** @return HasOne<BookingRide, $this> */
    public function ride(): HasOne
    {
        return $this->hasOne(BookingRide::class);
    }

    /** @return HasMany<BookingQuestionnaireResponse, $this> */
    public function questionnaireResponses(): HasMany
    {
        return $this->hasMany(BookingQuestionnaireResponse::class);
    }

    /**
     * Bookings that still occupy their slot.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::liveValues());
    }

    /**
     * Bookings whose OCCUPIED range overlaps the given one — buffers
     * included, so turnaround is respected rather than merely displayed.
     *
     * Half-open, so an occupancy ending exactly as another begins does not
     * conflict.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $from, \DateTimeInterface $until): void
    {
        $query->where('occupies_from', '<', $until)
            ->where('occupies_until', '>', $from);
    }

    /** @return BelongsTo<CollectionItem, $this> */
    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class);
    }

    /**
     * True when the booking crosses a calendar-day boundary.
     */
    public function spansMidnight(): bool
    {
        return $this->starts_at->toDateString() !== $this->ends_at->copy()->subSecond()->toDateString();
    }
}
