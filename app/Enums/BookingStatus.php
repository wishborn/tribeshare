<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Active = 'active';
    case Completed = 'completed';
    case Denied = 'denied';
    case Cancelled = 'cancelled';
    case Bumped = 'bumped';

    /**
     * Statuses that occupy their slot, and so contend for overlap.
     *
     * A completed booking no longer blocks the slot; a cancelled, denied or
     * bumped one never did.
     *
     * @return array<int, self>
     */
    public static function live(): array
    {
        return [self::Pending, self::Confirmed, self::Active];
    }

    /**
     * @return array<int, string>
     */
    public static function liveValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::live());
    }

    public function isLive(): bool
    {
        return in_array($this, self::live(), true);
    }

    /**
     * Whether the clock may still move this booking along.
     */
    public function isClockDriven(): bool
    {
        return in_array($this, [self::Confirmed, self::Active], true);
    }
}
