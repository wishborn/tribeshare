<?php

namespace App\Enums;

enum SplitMode: string
{
    case Equal = 'equal';

    /**
     * A named member pays a set percentage; the rest divide the remainder.
     *
     * The payer is named explicitly rather than inferred from position, which
     * is what made this fragile in the prototype — cancellations reordered
     * the list and moved the obligation.
     */
    case Custom = 'custom';
}
