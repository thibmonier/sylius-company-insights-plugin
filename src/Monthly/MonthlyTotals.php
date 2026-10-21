<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Monthly;

/** Orders and tax-exclusive spend (minor units) of one currency in one month of the requested range. */
final readonly class MonthlyTotals
{
    public function __construct(
        public string $currencyCode,
        public int $monthIndex,
        public int $ordersCount,
        public int $spend,
    ) {
    }
}
