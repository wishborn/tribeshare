<?php

namespace App\Enums;

/**
 * The two delegated tiers.
 *
 * Owners are absent deliberately — they always hold every power, so they are
 * never subject to a power table.
 */
enum PowerTier: string
{
    case Manager = 'manager';
    case Admin = 'admin';
}
