<?php

namespace App\Models;

use Database\Factories\BookingEventAttendeeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingEventAttendee extends Model
{
    /** @use HasFactory<BookingEventAttendeeFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'left_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BookingEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(BookingEvent::class, 'booking_event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
