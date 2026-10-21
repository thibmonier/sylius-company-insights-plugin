<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Statistics;

/** What a company bought in a period. */
final readonly class PeriodStatistics
{
    /**
     * @param array<string, CurrencyTotals> $totals Per currency code
     * @param int                           $activeBuyers Distinct customers with at least one order, whatever the currency
     */
    public function __construct(
        public array $totals,
        public int $activeBuyers,
    ) {
    }

    public function ordersCount(): int
    {
        return array_sum(array_map(static fn (CurrencyTotals $totals): int => $totals->ordersCount, $this->totals));
    }
}
