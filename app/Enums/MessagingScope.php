<?php

namespace App\Enums;

/**
 * Who a member may start a conversation with.
 *
 * Set per region, falling back to the platform default. Every value here is
 * a scoped concept, which is why a single global field never fitted.
 */
enum MessagingScope: string
{
    /** Members who share an LLC. */
    case LlcOnly = 'llc_only';

    /** Members who share an asset pool. */
    case PoolOnly = 'pool_only';

    /** Anyone in the same region. */
    case Regional = 'regional';

    /** Anyone at all. */
    case Anyone = 'anyone';

    public function label(): string
    {
        return match ($this) {
            self::LlcOnly => 'Members of the same LLC',
            self::PoolOnly => 'Members of the same asset pool',
            self::Regional => 'Anyone in the region',
            self::Anyone => 'Anyone',
        };
    }
}
