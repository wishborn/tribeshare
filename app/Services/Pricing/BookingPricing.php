<?php

namespace App\Services\Pricing;

/**
 * A fully-resolved price breakdown, ready to be frozen onto a booking.
 *
 * Every figure a booking needs to explain its own total, years later, after
 * the asset's prices and the LLC's and region's fee rates have all moved on.
 */
readonly class BookingPricing
{
    public function __construct(
        public int $basePriceCents,
        public float $multiplierPct,
        public int $adjustedCents,
        public int $groupSize,
        public int $groupTotalCents,
        public int $perPersonCents,
        public int $feeBaseCents,
        public float $llcFeePct,
        public int $llcFeeMinCents,
        public int $llcFeeCents,
        public float $regionFeePct,
        public int $regionFeeMinCents,
        public int $regionFeeCents,
        public int $totalCents,
    ) {}

    /**
     * The columns frozen onto the booking row.
     *
     * @return array<string, int|float>
     */
    public function toBookingAttributes(): array
    {
        return [
            'base_price_cents' => $this->basePriceCents,
            'price_multiplier_pct' => $this->multiplierPct,
            'per_person_cents' => $this->perPersonCents,
            'fee_base_cents' => $this->feeBaseCents,
            'llc_fee_pct' => $this->llcFeePct,
            'llc_fee_min_cents' => $this->llcFeeMinCents,
            'llc_fee_cents' => $this->llcFeeCents,
            'region_fee_pct' => $this->regionFeePct,
            'region_fee_min_cents' => $this->regionFeeMinCents,
            'region_fee_cents' => $this->regionFeeCents,
            'total_cents' => $this->totalCents,
        ];
    }
}
