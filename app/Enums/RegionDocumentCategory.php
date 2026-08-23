<?php

namespace App\Enums;

/**
 * A region's document library.
 *
 * `Claims` is here because the prototype filed claims among the documents.
 * Claims are now their own entity with their own lifecycle, so this category
 * holds the paperwork *about* claims rather than the claims themselves.
 */
enum RegionDocumentCategory: string
{
    case Insurance = 'insurance';
    case Claims = 'claims';
    case Contracts = 'contracts';
    case ConstructionLoans = 'construction_loans';

    public function label(): string
    {
        return match ($this) {
            self::Insurance => 'Insurance',
            self::Claims => 'Claims',
            self::Contracts => 'Contracts',
            self::ConstructionLoans => 'Construction loans',
        };
    }
}
