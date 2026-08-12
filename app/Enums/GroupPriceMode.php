<?php

namespace App\Enums;

enum GroupPriceMode: string
{
    /** Each head pays the adjusted price. */
    case None = 'none';

    /** The whole group is scaled by a configured multiplier. */
    case Multiplier = 'multiplier';

    /** One base price plus a premium for each additional person. */
    case Premium = 'premium';
}
