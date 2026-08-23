<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Withdrawn = 'withdrawn';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isResolved(): bool
    {
        return $this !== self::Pending;
    }
}
