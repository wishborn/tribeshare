<?php

namespace App\Models;

use Database\Factories\BookingOfferFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingOffer extends Model
{
    /** @use HasFactory<BookingOfferFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'retracted_at' => 'datetime',
            'giver_pct' => 'float',
            'picker_pct' => 'float',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->picked_up_at === null && $this->retracted_at === null;
    }
}
