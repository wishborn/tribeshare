<?php

namespace App\Enums;

/**
 * Derived per charge by FIFO allocation of income and confirmed payments,
 * then by age. Never stored.
 */
enum ChargeStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Pending = 'pending';
    case Due = 'due';
    case Overdue = 'overdue';

    /**
     * An overdue charge suspends the member from booking.
     */
    public function suspendsBooking(): bool
    {
        return $this === self::Overdue;
    }
}
