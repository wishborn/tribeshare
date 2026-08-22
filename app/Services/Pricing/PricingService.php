<?php

namespace App\Services\Pricing;

use App\Enums\GroupPriceMode;
use App\Models\Asset;
use InvalidArgumentException;

/**
 * Booking price computation.
 *
 * Prices are ALWAYS computed here, server-side, and never accepted from the
 * client — the prototype built the whole priced booking in the browser and
 * trusted it, so any price could be submitted.
 *
 * The base price and the per-meso multiplier arrive as inputs because they
 * come from slot configuration and calendar rules, neither of which is
 * specified yet. Everything downstream of them is specified and lives here.
 */
class PricingService
{
    /**
     * @param  int  $basePriceCents  from the slot type, or the service type for person-assets
     * @param  float  $multiplierPct  mean per-meso uplift across the booked range
     */
    /**
     * @param  float  $multiplierPct  100 means normal price (see below)
     */
    public function price(
        Asset $asset,
        int $basePriceCents,
        float $multiplierPct = 100.0,
        int $groupSize = 1,
    ): BookingPricing {
        if ($groupSize < 1) {
            throw new InvalidArgumentException('Group size must be at least 1.');
        }

        $llc = $asset->llc;
        $region = $llc->region;

        // 100 leaves the price alone; 150 adds half again; 50 halves it.
        //
        // NOT an uplift. The prototype computed `base * (1 + m/100)` while
        // its own authoring panel defaulted the multiplier to 100, so every
        // slot saved at the default silently priced at double — and an
        // unruled meso, contributing 0, came out cheaper than a deliberate
        // one.
        $adjusted = (int) round($basePriceCents * $multiplierPct / 100);

        $groupTotal = $this->groupTotal($asset, $adjusted, $groupSize);

        // Fees are charged PER PERSON, not on the group total.
        $perPerson = (int) round($groupTotal / $groupSize);

        // A nominally free booking still attracts fees, computed on the
        // configured floor rather than on zero.
        $feeBase = max($perPerson, (int) config('tribeshare.fees.minimum_fee_base_cents'));

        $llcFee = $this->fee($feeBase, (float) $llc->booking_fee_pct, (int) $llc->booking_fee_min_cents);
        $regionFee = $this->fee($feeBase, (float) $region->booking_fee_pct, (int) $region->booking_fee_min_cents);

        return new BookingPricing(
            basePriceCents: $basePriceCents,
            multiplierPct: $multiplierPct,
            adjustedCents: $adjusted,
            groupSize: $groupSize,
            groupTotalCents: $groupTotal,
            perPersonCents: $perPerson,
            feeBaseCents: $feeBase,
            llcFeePct: (float) $llc->booking_fee_pct,
            llcFeeMinCents: (int) $llc->booking_fee_min_cents,
            llcFeeCents: $llcFee,
            regionFeePct: (float) $region->booking_fee_pct,
            regionFeeMinCents: (int) $region->booking_fee_min_cents,
            regionFeeCents: $regionFee,
            totalCents: $perPerson + $llcFee + $regionFee,
        );
    }

    /**
     * Fees are the greater of the percentage and the flat minimum. LLCs and
     * regions use the same shape — the prototype gave the minimum to regions
     * only.
     */
    public function fee(int $feeBaseCents, float $pct, int $minCents): int
    {
        $max = (float) config('tribeshare.fees.max_percent');

        $clamped = max(0.0, min($max, $pct));

        return max((int) round($feeBaseCents * $clamped / 100), max(0, $minCents));
    }

    /**
     * How much of a booking's base price is redirected from the asset owner
     * to the LLC and region as voluntary contributions.
     *
     * @return array{llc_cents: int, region_cents: int, owner_cents: int}
     */
    public function contributionSplit(Asset $asset, int $perPersonCents): array
    {
        $llcPct = $asset->voluntary_contrib_llc_pct;
        $regionPct = $asset->voluntary_contrib_region_pct;

        $this->assertContributionsValid($llcPct, $regionPct);

        $llcCents = (int) round($perPersonCents * $llcPct / 100);
        $regionCents = (int) round($perPersonCents * $regionPct / 100);

        return [
            'llc_cents' => $llcCents,
            'region_cents' => $regionCents,
            // Guaranteed non-negative by the validation above, so no clamp is
            // needed — clamping is what let the prototype credit more than it
            // debited.
            'owner_cents' => $perPersonCents - $llcCents - $regionCents,
        ];
    }

    /**
     * Contributions may never total more than the whole booking, or a
     * booking would credit more than it debits and money would appear from
     * nowhere. Enforced when asset settings are saved.
     */
    public function assertContributionsValid(float $llcPct, float $regionPct): void
    {
        $max = (float) config('tribeshare.fees.max_total_contribution_percent');

        if ($llcPct < 0 || $regionPct < 0) {
            throw new InvalidArgumentException('Voluntary contributions cannot be negative.');
        }

        if ($llcPct + $regionPct > $max) {
            throw new InvalidArgumentException(
                "Voluntary contributions total {$llcPct}% + {$regionPct}%, which exceeds the {$max}% limit."
            );
        }
    }

    private function groupTotal(Asset $asset, int $adjustedCents, int $groupSize): int
    {
        if ($groupSize === 1) {
            return $adjustedCents;
        }

        return match ($asset->group_price_mode) {
            GroupPriceMode::Multiplier => (int) round(
                $adjustedCents * $asset->group_multiplier * $groupSize
            ),
            GroupPriceMode::Premium => $adjustedCents + $asset->group_premium_cents * ($groupSize - 1),
            GroupPriceMode::None => $adjustedCents * $groupSize,
        };
    }
}
