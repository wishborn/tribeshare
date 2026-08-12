<?php

namespace App\Enums;

enum LedgerDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    /**
     * Amounts are always stored positive; the direction carries the sign.
     */
    public function sign(): int
    {
        return $this === self::Debit ? 1 : -1;
    }

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }
}
