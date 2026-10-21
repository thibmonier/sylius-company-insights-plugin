<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Indicators;

/** A value of the period with its variation against the previous period. */
final readonly class Indicator
{
    public function __construct(
        public int $value,
        public int $previous,
        public Variation $variation,
    ) {
    }

    public static function compare(int $current, int $previous): self
    {
        return new self($current, $previous, Variation::between($current, $previous));
    }
}
