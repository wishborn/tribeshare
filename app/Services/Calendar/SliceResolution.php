<?php

namespace App\Services\Calendar;

/**
 * What a range of time resolves to, once every rule covering it is read.
 */
readonly class SliceResolution
{
    /**
     * @param  array<int, string>  $reasons  why it is unbookable, when it is
     */
    public function __construct(
        public bool $bookable,
        public float $multiplierPct,
        public int $mesosCovered,
        public int $mesosRequested,
        public array $reasons = [],
    ) {}

    /**
     * Every meso in the range carried a rule.
     *
     * Rules opt slices IN, so a partially-covered range is not bookable —
     * an unruled meso is closed, not open at base price.
     */
    public function isFullyCovered(): bool
    {
        return $this->mesosCovered === $this->mesosRequested;
    }

    public static function closed(int $mesosRequested, string $reason): self
    {
        return new self(
            bookable: false,
            multiplierPct: 100.0,
            mesosCovered: 0,
            mesosRequested: $mesosRequested,
            reasons: [$reason],
        );
    }
}
