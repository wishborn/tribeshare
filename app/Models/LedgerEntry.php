<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use Carbon\CarbonImmutable;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Append-only. Nothing in this table is ever updated or deleted — a
 * correction is a new balancing entry pointing at what it reverses.
 *
 * @property string $id
 * @property LedgerDirection $direction
 * @property LedgerLabel $label
 * @property int $amount_cents
 * @property string|null $description
 * @property string $month_key
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $due_at
 * @property string|null $reverses_id
 * @property string $owner_type
 * @property string $owner_id
 * @property string|null $booking_id
 * @property string|null $asset_id
 */
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'direction' => LedgerDirection::class,
            'label' => LedgerLabel::class,
            'due_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The append-only rule, enforced rather than documented. Without this
        // a stray save() would silently rewrite financial history.
        static::updating(function (): never {
            throw new RuntimeException(
                'Ledger entries are immutable. Post a balancing entry instead of editing one.'
            );
        });

        static::deleting(function (): never {
            throw new RuntimeException(
                'Ledger entries are immutable. Post a balancing entry instead of deleting one.'
            );
        });

        static::creating(function (LedgerEntry $entry): void {
            // month_key is always derived, never hand-set, so period
            // reporting cannot drift from the entry's own timestamp.
            $entry->created_at ??= now();
            $entry->month_key = $entry->created_at->format('Y-m');
        });
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<LedgerEntry, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'reverses_id');
    }

    /**
     * The amount as it affects a balance: debits positive, credits negative.
     */
    public function signedAmountCents(): int
    {
        return $this->amount_cents * $this->direction->sign();
    }

    /** @param  Builder<LedgerEntry>  $query */
    public function scopeDebits(Builder $query): void
    {
        $query->where('direction', LedgerDirection::Debit);
    }

    /** @param  Builder<LedgerEntry>  $query */
    public function scopeCredits(Builder $query): void
    {
        $query->where('direction', LedgerDirection::Credit);
    }

    /** @param  Builder<LedgerEntry>  $query */
    public function scopeOwnedBy(Builder $query, Model $owner): void
    {
        $query->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }
}
