<?php

namespace App\Enums;

enum PaymentStatus: string
{
    /** Submitted by the member; counts toward nothing yet. */
    case Pending = 'pending';

    /** Confirmed by an RCM or region owner; now counts toward balances. */
    case Confirmed = 'confirmed';
}
