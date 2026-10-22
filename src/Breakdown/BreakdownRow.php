<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

/** One site or one value of an axis; a null label gathers what has none (other addresses, value not set). */
final readonly class BreakdownRow
{
    public function __construct(
        public ?string $label,
        public int $ordersCount,
        public int $spend,
    ) {
    }
}
