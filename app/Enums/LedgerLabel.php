<?php

namespace App\Enums;

enum LedgerLabel: string
{
    /** The booker's charge for the asset itself. */
    case AssetCharge = 'asset_charge';

    /** The asset owner's income, net of any voluntary contributions. */
    case AssetIncome = 'asset_income';

    case LlcFee = 'llc_fee';
    case RegionalFee = 'regional_fee';

    /** Metered usage, charged after an accepted unit report. */
    case UnitCharge = 'unit_charge';

    /** Draws down credit when a payout is approved. */
    case Payout = 'payout';

    /** A balancing entry correcting an earlier one. */
    case Reversal = 'reversal';
}
