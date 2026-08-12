<?php

namespace App\Models;

use App\Enums\GroupPriceMode;
use App\Enums\SplitMode;
use Database\Factories\BookingGroupFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingGroup extends Model
{
    /** @use HasFactory<BookingGroupFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'split_mode' => SplitMode::class,
            'price_mode' => GroupPriceMode::class,
            'custom_pct' => 'float',
        ];
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customPayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custom_payer_user_id');
    }

    /**
     * Bookings in this group that still stand.
     *
     * @return HasMany<Booking, $this>
     */
    public function survivingBookings(): HasMany
    {
        return $this->bookings()->live();
    }
}
